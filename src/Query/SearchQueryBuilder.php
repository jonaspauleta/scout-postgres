<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Query;

use ApexScout\ScoutPostgres\Contracts\PostgresSearchable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

/**
 * @phpstan-type ScoutPostgresConfig array{
 *   text_search_config: string,
 *   fts_weight: float,
 *   trigram_weight: float,
 *   trigram_threshold: float,
 *   rank_function: string,
 *   rank_weights: array<int, float>,
 *   rank_normalization: int,
 * }
 */
final readonly class SearchQueryBuilder
{
    /**
     * @param  array<string, mixed>  $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings,
    ) {}

    /**
     * @param  Builder<Model>  $builder
     */
    public static function forSearch(Builder $builder): ?self
    {
        return self::build($builder, limit: null, offset: null);
    }

    /**
     * @param  Builder<Model>  $builder
     */
    public static function forPaginate(Builder $builder, int $perPage, int $page): ?self
    {
        $offset = max(0, ($page - 1) * $perPage);

        return self::build($builder, limit: $perPage, offset: $offset);
    }

    /**
     * @return ScoutPostgresConfig
     */
    public static function resolveConfig(Model $model): array
    {
        $override = $model instanceof PostgresSearchable
            ? $model->scoutPostgresConfig()
            : [];

        return [
            'text_search_config' => self::resolveString($override, 'text_search_config', 'simple_unaccent'),
            'fts_weight' => self::resolveFloat($override, 'fts_weight', 2.0),
            'trigram_weight' => self::resolveFloat($override, 'trigram_weight', 1.0),
            'trigram_threshold' => self::resolveFloat($override, 'trigram_threshold', 0.3),
            'rank_function' => self::resolveString($override, 'rank_function', 'ts_rank'),
            'rank_weights' => self::resolveWeights($override),
            'rank_normalization' => self::resolveInt($override, 'rank_normalization', 32),
        ];
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private static function build(Builder $builder, ?int $limit, ?int $offset): ?self
    {
        $query = trim($builder->query);
        if ($query === '') {
            return null;
        }

        $model = $builder->model;
        $table = $builder->index ?? $model->getTable();

        $prefixQuery = QueryEscaper::buildPrefixQuery($query);
        $rawTrigram = QueryEscaper::normaliseForTrigram($query);

        // PDO_PGSQL with native prepares (Laravel's default) rewrites each `:name`
        // occurrence into a separate $n placeholder. Any named placeholder that
        // appears more than once in the SQL must have a distinct binding per
        // occurrence. We duplicate `:raw` under `:raw_trgm` and branch on the
        // prefix query in PHP so `:prefix_query` appears at most once.
        $bindings = [
            'query' => $query,
            'prefix_query' => $prefixQuery,
            'raw' => $rawTrigram,
            'raw_trgm' => $rawTrigram,
        ];

        $config = self::resolveConfig($model);

        $wheresSql = self::compileWheres($builder->wheres, $bindings);
        $whereInsSql = self::compileWhereIns($builder->whereIns, $bindings, negated: false);
        $whereNotInsSql = self::compileWhereIns($builder->whereNotIns, $bindings, negated: true);

        $orderSql = self::compileOrders($builder->orders, $model);

        $builderLimit = $builder->limit;
        $limitSql = $limit === null
            ? (is_int($builderLimit) ? ' LIMIT '.$builderLimit : '')
            : sprintf(' LIMIT %d OFFSET %d', $limit, (int) $offset);

        $rankFn = $config['rank_function'];
        $rankNorm = $config['rank_normalization'];
        $ftsWeight = $config['fts_weight'];
        $trgmWeight = $config['trigram_weight'];
        $tsConfig = $config['text_search_config'];
        $weightsArray = '{'.implode(',', $config['rank_weights']).'}';

        $pfxExpr = $prefixQuery === ''
            ? 'NULL'
            : sprintf("to_tsquery('%s', :prefix_query)", $tsConfig);

        if ($prefixQuery === '') {
            unset($bindings['prefix_query']);
        }

        $totalCountClause = self::wantsTotalCount($builder)
            ? ",\n  COUNT(*) OVER() AS _total"
            : '';

        $sql = <<<SQL
WITH q AS (
  SELECT
    websearch_to_tsquery('{$tsConfig}', :query) AS ws,
    {$pfxExpr} AS pfx
)
SELECT "{$model->getKeyName()}" AS id,
  (
    CASE WHEN search_vector @@ COALESCE(q.ws || q.pfx, q.ws)
         THEN {$rankFn}('{$weightsArray}'::real[], search_vector, q.ws, {$rankNorm})
         ELSE 0
    END
  ) * {$ftsWeight}
  + COALESCE(similarity(search_text, :raw), 0) * {$trgmWeight}
    AS _score{$totalCountClause}
FROM "{$table}", q
WHERE (
    search_vector @@ COALESCE(q.ws || q.pfx, q.ws)
    OR search_text % :raw_trgm
){$wheresSql}{$whereInsSql}{$whereNotInsSql}
{$orderSql}{$limitSql}
SQL;

        return new self($sql, $bindings);
    }

    /**
     * Whether the engine should compute a total match count via `COUNT(*) OVER()`.
     *
     * Default: true. Users can opt out per-query for broader scans where the
     * window-aggregate over the full match set is the latency bottleneck:
     *
     *     Book::search('foo')->options(['scout_postgres' => ['total_count' => false]])
     *
     * When opted out, `getTotalCount()` reflects only the rows returned by the
     * current page, not the size of the underlying match set.
     *
     * @param  Builder<Model>  $builder
     */
    private static function wantsTotalCount(Builder $builder): bool
    {
        /** @var array<string, mixed> $options */
        $options = $builder->options;
        $scout = $options['scout_postgres'] ?? null;

        if (! is_array($scout)) {
            return true;
        }

        if (! array_key_exists('total_count', $scout)) {
            return true;
        }

        return (bool) $scout['total_count'];
    }

    /**
     * Scout 11 stores wheres as a list of assoc arrays:
     * [['field' => ..., 'operator' => ..., 'value' => ...], ...].
     *
     * Deviation from plan: plan assumed `[$field => $value]` or
     * `[$field => [$op, $val]]` storage, but Scout 11 `Builder::where()`
     * actually appends `['field', 'operator', 'value']` tuples to a list.
     * The `normaliseWhere` helper is therefore unnecessary.
     *
     * @param  array<int|string, mixed>  $wheres
     * @param  array<string, mixed>  $bindings
     */
    private static function compileWheres(array $wheres, array &$bindings): string
    {
        $sql = '';
        $i = 0;

        foreach ($wheres as $where) {
            if (! is_array($where)) {
                continue;
            }

            $column = self::scalarString($where['field'] ?? null);
            $operator = self::scalarString($where['operator'] ?? null, '=');
            $value = $where['value'] ?? null;

            if ($column === '') {
                continue;
            }

            if ($column === '__soft_deleted') {
                $flag = is_int($value) || is_string($value) ? (int) $value : 0;
                $sql .= $flag === 0
                    ? ' AND "deleted_at" IS NULL'
                    : ' AND "deleted_at" IS NOT NULL';

                continue;
            }

            $placeholder = 'where_'.$i++;
            $bindings[$placeholder] = $value;
            $sql .= ' AND "'.$column.'" '.$operator.' :'.$placeholder;
        }

        return $sql;
    }

    /**
     * @param  array<int|string, mixed>  $whereIns
     * @param  array<string, mixed>  $bindings
     */
    private static function compileWhereIns(array $whereIns, array &$bindings, bool $negated): string
    {
        $sql = '';
        $i = 0;
        $op = $negated ? 'NOT IN' : 'IN';
        $prefix = $negated ? 'notin' : 'in';

        foreach ($whereIns as $column => $values) {
            if (! is_string($column)) {
                continue;
            }
            if (! is_array($values)) {
                continue;
            }
            if ($values === []) {
                $sql .= $negated ? '' : ' AND 1=0'; // empty IN ⇒ impossible match; empty NOT IN ⇒ tautology

                continue;
            }

            $placeholders = [];
            foreach ($values as $v) {
                $key = $prefix.'_'.$i++;
                $bindings[$key] = $v;
                $placeholders[] = ':'.$key;
            }

            $sql .= ' AND "'.$column.'" '.$op.' ('.implode(', ', $placeholders).')';
        }

        return $sql;
    }

    /**
     * @param  array<int|string, mixed>  $orders
     */
    private static function compileOrders(array $orders, Model $model): string
    {
        if ($orders === []) {
            return 'ORDER BY _score DESC, "'.$model->getKeyName().'" ASC';
        }

        $parts = [];
        foreach ($orders as $o) {
            if (! is_array($o)) {
                continue;
            }
            $column = self::scalarString($o['column'] ?? null);
            $direction = self::scalarString($o['direction'] ?? null, 'asc');
            if ($column === '') {
                continue;
            }
            $parts[] = '"'.$column.'" '.$direction;
        }

        return 'ORDER BY '.implode(', ', $parts);
    }

    private static function scalarString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private static function resolveString(array $override, string $key, string $default): string
    {
        if (array_key_exists($key, $override)) {
            $value = $override[$key];
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $value = config('scout-postgres.'.$key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private static function resolveFloat(array $override, string $key, float $default): float
    {
        if (array_key_exists($key, $override)) {
            $value = $override[$key];
            if (is_int($value) || is_float($value)) {
                return (float) $value;
            }
        }

        $value = config('scout-postgres.'.$key, $default);

        return is_int($value) || is_float($value) ? (float) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private static function resolveInt(array $override, string $key, int $default): int
    {
        if (array_key_exists($key, $override)) {
            $value = $override[$key];
            if (is_int($value)) {
                return $value;
            }
        }

        $value = config('scout-postgres.'.$key, $default);

        return is_int($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<int, float>
     */
    private static function resolveWeights(array $override): array
    {
        $default = [0.1, 0.2, 0.4, 1.0];

        $raw = array_key_exists('rank_weights', $override)
            ? $override['rank_weights']
            : config('scout-postgres.rank_weights', $default);

        if (! is_array($raw)) {
            return $default;
        }

        $result = [];
        foreach (array_values($raw) as $v) {
            if (is_int($v) || is_float($v)) {
                $result[] = (float) $v;
            }
        }

        return $result === [] ? $default : $result;
    }
}
