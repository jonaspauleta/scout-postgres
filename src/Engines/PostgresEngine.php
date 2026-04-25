<?php

declare(strict_types=1);

namespace ScoutPostgres\Engines;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Searchable;
use ScoutPostgres\Exceptions\ModelNotSearchableException;
use ScoutPostgres\Exceptions\UnsupportedDriverException;
use ScoutPostgres\Query\SearchQueryBuilder;
use stdClass;

/**
 * @phpstan-import-type ScoutPostgresConfig from SearchQueryBuilder
 *
 * @phpstan-type ScoutHit array{id: int|string, _score: float}
 * @phpstan-type ScoutResults array{hits: list<ScoutHit>, total: int}
 */
final class PostgresEngine extends Engine
{
    /** @var array<string, bool> */
    private array $verifiedTables = [];

    /**
     * @param  EloquentCollection<int, Model>  $models
     */
    public function update($models): void {}

    /**
     * @param  EloquentCollection<int, Model>  $models
     */
    public function delete($models): void {}

    /**
     * @param  Builder<Model>  $builder
     * @return ScoutResults
     */
    public function search(Builder $builder): array
    {
        return $this->performSearch($builder, null, null);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  int|numeric-string  $perPage
     * @param  int|numeric-string  $page
     * @return ScoutResults
     */
    public function paginate(Builder $builder, $perPage, $page): array
    {
        return $this->performSearch($builder, (int) $perPage, (int) $page);
    }

    /**
     * @param  ScoutResults  $results
     * @return Collection<int, int|string>
     */
    public function mapIds($results): Collection
    {
        return collect($this->extractIds($results));
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  ScoutResults  $results
     * @return EloquentCollection<int, Model>
     */
    public function map(Builder $builder, $results, $model): EloquentCollection
    {
        if ($results['hits'] === []) {
            return $model->newCollection();
        }

        $ids = $this->extractIds($results);
        $position = $this->buildPositionMap($ids);

        $fetched = $this->queryModelsByIds($model, $builder, $ids)->get();

        return $fetched
            ->filter(fn (Model $m): bool => array_key_exists($this->modelKey($m), $position))
            ->sortBy(fn (Model $m): int => $position[$this->modelKey($m)])
            ->values();
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  ScoutResults  $results
     * @return LazyCollection<int, Model>
     */
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if ($results['hits'] === []) {
            return LazyCollection::make();
        }

        $ids = $this->extractIds($results);
        $position = $this->buildPositionMap($ids);

        $cursor = $this->queryModelsByIds($model, $builder, $ids)->cursor();

        return $cursor
            ->filter(fn (Model $m): bool => array_key_exists($this->modelKey($m), $position))
            ->sortBy(fn (Model $m): int => $position[$this->modelKey($m)])
            ->values();
    }

    /**
     * @param  ScoutResults  $results
     * @return list<int|string>
     */
    private function extractIds(array $results): array
    {
        $ids = [];
        foreach ($results['hits'] as $hit) {
            $ids[] = $hit['id'];
        }

        return $ids;
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<string, int>
     */
    private function buildPositionMap(array $ids): array
    {
        $map = [];
        foreach ($ids as $index => $id) {
            $map[(string) $id] = $index;
        }

        return $map;
    }

    private function modelKey(Model $model): string
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? (string) $key : '';
    }

    /**
     * @param  ScoutResults  $results
     */
    public function getTotalCount($results): int
    {
        return $results['total'];
    }

    public function flush($model): void {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function createIndex($name, array $options = []): void {}

    public function deleteIndex($name): void {}

    /**
     * @param  Builder<Model>  $builder
     * @return ScoutResults
     */
    private function performSearch(Builder $builder, ?int $perPage, ?int $page): array
    {
        $model = $builder->model;
        $connection = $this->resolveConnection($model);

        $this->ensureSearchable($connection, $model->getTable());

        $config = SearchQueryBuilder::resolveConfig($model);
        $strategy = $config['query_strategy'];
        $preludes = $this->buildPreludes($config);

        // Short-prefix fast path: single short token bypasses both
        // websearch_to_tsquery and the trigram pass entirely.
        if ($config['prefix_fast_path']
            && SearchQueryBuilder::isShortPrefixQuery($builder->query, $config['prefix_fast_path_max_length'])) {
            $compiled = $perPage === null
                ? SearchQueryBuilder::forSearch($builder, mode: 'prefix_fast_path')
                : SearchQueryBuilder::forPaginate($builder, $perPage, (int) $page, mode: 'prefix_fast_path');

            return $this->executeCompiled($connection, $compiled, $preludes);
        }

        // "fts_only" and "hybrid" are single-pass — compile once, run once.
        if ($strategy === 'fts_only' || $strategy === 'hybrid') {
            $compiled = $perPage === null
                ? SearchQueryBuilder::forSearch($builder, mode: $strategy)
                : SearchQueryBuilder::forPaginate($builder, $perPage, (int) $page, mode: $strategy);

            return $this->executeCompiled($connection, $compiled, $preludes);
        }

        // "adaptive" — try FTS-only first; only fall back to hybrid when FTS
        // recall is insufficient (typo / fuzzy queries that need trigram).
        $ftsCompiled = $perPage === null
            ? SearchQueryBuilder::forSearch($builder, mode: 'fts_only')
            : SearchQueryBuilder::forPaginate($builder, $perPage, (int) $page, mode: 'fts_only');

        $ftsResults = $this->executeCompiled($connection, $ftsCompiled, $preludes);

        if ($this->ftsRecallSufficient($ftsResults, $perPage)) {
            return $ftsResults;
        }

        $hybridCompiled = $perPage === null
            ? SearchQueryBuilder::forSearch($builder, mode: 'hybrid')
            : SearchQueryBuilder::forPaginate($builder, $perPage, (int) $page, mode: 'hybrid');

        return $this->executeCompiled($connection, $hybridCompiled, $preludes);
    }

    /**
     * @param  ScoutPostgresConfig  $config
     * @return list<string>
     */
    private function buildPreludes(array $config): array
    {
        $preludes = [];

        if ($config['disable_jit']) {
            $preludes[] = 'SET LOCAL jit = off';
        }

        $preludes[] = sprintf(
            'SET LOCAL %s = %F',
            SearchQueryBuilder::trigramThresholdVariable($config['trigram_function']),
            $config['trigram_threshold'],
        );

        return $preludes;
    }

    /**
     * @param  list<string>  $preludes
     * @return ScoutResults
     */
    private function executeCompiled(ConnectionInterface $connection, ?SearchQueryBuilder $compiled, array $preludes): array
    {
        if (! $compiled instanceof SearchQueryBuilder) {
            return ['hits' => [], 'total' => 0];
        }

        $engine = $this;

        /** @var ScoutResults $results */
        $results = $connection->transaction(static function () use ($engine, $connection, $compiled, $preludes): array {
            foreach ($preludes as $prelude) {
                $connection->statement($prelude);
            }

            $rows = $connection->select($compiled->sql, $compiled->bindings);

            return $engine->formatRows($rows);
        });

        return $results;
    }

    /**
     * @param  ScoutResults  $results
     */
    private function ftsRecallSufficient(array $results, ?int $perPage): bool
    {
        $hitCount = count($results['hits']);

        if ($hitCount === 0) {
            return false;
        }

        if ($perPage === null) {
            return true;
        }

        return $hitCount >= $perPage;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return ScoutResults
     */
    private function formatRows(array $rows): array
    {
        $hits = [];
        $total = 0;
        $totalSeen = false;

        foreach ($rows as $row) {
            if (! $row instanceof stdClass) {
                continue;
            }

            $hits[] = [
                'id' => $this->normaliseId($row->id ?? null),
                '_score' => $this->normaliseScore($row->_score ?? null),
            ];

            if (! $totalSeen) {
                $rawTotal = $row->_total ?? null;
                if (is_int($rawTotal) || (is_string($rawTotal) && is_numeric($rawTotal))) {
                    $total = (int) $rawTotal;
                    $totalSeen = true;
                }
            }
        }

        // When the caller opted out of `COUNT(*) OVER()` via
        // `->options(['scout_postgres' => ['total_count' => false]])`, the SQL
        // has no `_total` column. Fall back to the current page size — the
        // user knowingly traded an accurate total for lower latency.
        if (! $totalSeen) {
            $total = count($hits);
        }

        return [
            'hits' => $hits,
            'total' => $total,
        ];
    }

    private function normaliseId(mixed $id): int|string
    {
        if (is_int($id) || is_string($id)) {
            return $id;
        }

        if ($id === null) {
            return '';
        }

        return (string) (is_scalar($id) ? $id : '');
    }

    private function normaliseScore(mixed $score): float
    {
        if (is_int($score) || is_float($score)) {
            return (float) $score;
        }

        if (is_string($score) && is_numeric($score)) {
            return (float) $score;
        }

        return 0.0;
    }

    /**
     * Narrow a Searchable model to a typed Eloquent query for key-based lookup.
     *
     * @param  Builder<Model>  $builder
     * @param  list<int|string>  $ids
     * @return EloquentBuilder<Model>
     */
    private function queryModelsByIds(Model $model, Builder $builder, array $ids): EloquentBuilder
    {
        if (! in_array(Searchable::class, class_uses_recursive($model), true)) {
            throw ModelNotSearchableException::for($model->getTable());
        }

        if (! method_exists($model, 'queryScoutModelsByIds')) {
            throw ModelNotSearchableException::for($model->getTable());
        }

        $query = call_user_func([$model, 'queryScoutModelsByIds'], $builder, $ids);

        if (! $query instanceof EloquentBuilder) {
            throw ModelNotSearchableException::for($model->getTable());
        }

        /** @var EloquentBuilder<Model> $query */
        return $query;
    }

    private function resolveConnection(Model $model): ConnectionInterface
    {
        $connection = $model->getConnection();
        $driver = $connection->getDriverName();

        if ($driver !== 'pgsql') {
            throw UnsupportedDriverException::for($driver);
        }

        return $connection;
    }

    private function ensureSearchable(ConnectionInterface $connection, string $table): void
    {
        if (isset($this->verifiedTables[$table])) {
            return;
        }

        $rows = $connection->select(
            'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
            [$table, 'search_vector'],
        );

        if ($rows === []) {
            throw ModelNotSearchableException::for($table);
        }

        $this->verifiedTables[$table] = true;
    }
}
