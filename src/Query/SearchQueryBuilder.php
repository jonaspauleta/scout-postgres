<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Query;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

final readonly class SearchQueryBuilder
{
    /**
     * @param  array<string, mixed>  $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings,
    ) {}

    public static function forSearch(Builder $builder): ?self
    {
        return self::build($builder, limit: null, offset: null);
    }

    public static function forPaginate(Builder $builder, int $perPage, int $page): ?self
    {
        $offset = max(0, ($page - 1) * $perPage);

        return self::build($builder, limit: $perPage, offset: $offset);
    }

    /**
     * @return array{
     *   text_search_config: string,
     *   fts_weight: float,
     *   trigram_weight: float,
     *   trigram_threshold: float,
     *   rank_function: string,
     *   rank_weights: array<int, float>,
     *   rank_normalization: int,
     * }
     */
    public static function resolveConfig(Model $model): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = config('scout-postgres', []);

        $override = method_exists($model, 'scoutPostgresConfig')
            ? $model->scoutPostgresConfig()
            : [];

        $merged = array_replace($defaults, $override);

        return [
            'text_search_config' => $merged['text_search_config'] ?? 'simple_unaccent',
            'fts_weight' => (float) ($merged['fts_weight'] ?? 2.0),
            'trigram_weight' => (float) ($merged['trigram_weight'] ?? 1.0),
            'trigram_threshold' => (float) ($merged['trigram_threshold'] ?? 0.15),
            'rank_function' => (string) ($merged['rank_function'] ?? 'ts_rank'),
            'rank_weights' => $merged['rank_weights'] ?? [0.1, 0.2, 0.4, 1.0],
            'rank_normalization' => (int) ($merged['rank_normalization'] ?? 32),
        ];
    }

    private static function build(Builder $builder, ?int $limit, ?int $offset): ?self
    {
        $query = mb_trim($builder->query);
        if ($query === '') {
            return null;
        }

        /** @var Model $model */
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
        $whereInsSql = self::compileWhereIns($builder->whereIns ?? [], $bindings, negated: false);
        $whereNotInsSql = self::compileWhereIns($builder->whereNotIns ?? [], $bindings, negated: true);

        $orderSql = self::compileOrders($builder->orders, $model);

        $limitSql = $limit === null
            ? (is_int($builder->limit) ? ' LIMIT '.$builder->limit : '')
            : sprintf(' LIMIT %d OFFSET %s', $limit, $offset);

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

        $sql = <<<SQL
WITH q AS (
  SELECT
    websearch_to_tsquery('{$tsConfig}', :query) AS ws,
    {$pfxExpr} AS pfx
)
SELECT "{$model->getKeyName()}" AS id,
  (
    CASE WHEN search_vector @@ COALESCE(q.ws && q.pfx, q.ws)
         THEN {$rankFn}('{$weightsArray}'::real[], search_vector, q.ws, {$rankNorm})
         ELSE 0
    END
  ) * {$ftsWeight}
  + COALESCE(similarity(search_text, :raw), 0) * {$trgmWeight}
    AS _score,
  COUNT(*) OVER() AS _total
FROM "{$table}", q
WHERE (
    search_vector @@ COALESCE(q.ws && q.pfx, q.ws)
    OR search_text % :raw_trgm
){$wheresSql}{$whereInsSql}{$whereNotInsSql}
{$orderSql}{$limitSql}
SQL;

        return new self($sql, $bindings);
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
     * @param  list<array{field: string, operator: string, value: mixed}>  $wheres
     * @param  array<string, mixed>  $bindings
     */
    private static function compileWheres(array $wheres, array &$bindings): string
    {
        $sql = '';
        $i = 0;

        foreach ($wheres as $where) {
            $column = $where['field'];
            $operator = $where['operator'];
            $value = $where['value'];

            if ($column === '__soft_deleted') {
                $sql .= (int) $value === 0
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
     * @param  array<string, array<int, mixed>>  $whereIns
     * @param  array<string, mixed>  $bindings
     */
    private static function compileWhereIns(array $whereIns, array &$bindings, bool $negated): string
    {
        $sql = '';
        $i = 0;
        $op = $negated ? 'NOT IN' : 'IN';
        $prefix = $negated ? 'notin' : 'in';

        foreach ($whereIns as $column => $values) {
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
     * @param  array<int, array{column: string, direction: string}>  $orders
     */
    private static function compileOrders(array $orders, Model $model): string
    {
        if ($orders === []) {
            return 'ORDER BY _score DESC, "'.$model->getKeyName().'" ASC';
        }

        $parts = array_map(
            fn (array $o): string => '"'.$o['column'].'" '.$o['direction'],
            $orders,
        );

        return 'ORDER BY '.implode(', ', $parts);
    }
}
