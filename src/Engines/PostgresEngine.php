<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Engines;

use ApexScout\ScoutPostgres\Exceptions\ModelNotSearchableException;
use ApexScout\ScoutPostgres\Exceptions\UnsupportedDriverException;
use ApexScout\ScoutPostgres\Query\SearchQueryBuilder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

final class PostgresEngine extends Engine
{
    /** @var array<string, bool> */
    private array $verifiedTables = [];

    public function update($models): void {}

    public function delete($models): void {}

    public function search(Builder $builder): array
    {
        return $this->performSearch($builder, null, null);
    }

    public function paginate(Builder $builder, $perPage, $page): array
    {
        return $this->performSearch($builder, (int) $perPage, (int) $page);
    }

    /**
     * @param  array{hits: list<array{id: int|string, _score: float}>, total: int}  $results
     */
    public function mapIds($results): Collection
    {
        return collect($results['hits'])->pluck('id');
    }

    /**
     * @param  array{hits: list<array{id: int|string, _score: float}>, total: int}  $results
     */
    public function map(Builder $builder, $results, $model): EloquentCollection
    {
        if ($results['hits'] === []) {
            return $model->newCollection();
        }

        $ids = collect($results['hits'])->pluck('id')->all();
        $position = array_flip($ids);

        return $model->queryScoutModelsByIds($builder, $ids)->get()
            ->filter(fn (Model $m): bool => array_key_exists((string) $m->getKey(), $position))
            ->sortBy(fn (Model $m) => $position[$m->getKey()])
            ->values();
    }

    /**
     * @param  array{hits: list<array{id: int|string, _score: float}>, total: int}  $results
     */
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if ($results['hits'] === []) {
            return LazyCollection::make();
        }

        $ids = collect($results['hits'])->pluck('id')->all();
        $position = array_flip($ids);

        return $model->queryScoutModelsByIds($builder, $ids)->cursor()
            ->filter(fn (Model $m): bool => array_key_exists((string) $m->getKey(), $position))
            ->sortBy(fn (Model $m) => $position[$m->getKey()])
            ->values();
    }

    /**
     * @param  array{hits: list<array{id: int|string, _score: float}>, total: int}  $results
     */
    public function getTotalCount($results): int
    {
        return $results['total'];
    }

    public function flush($model): void {}

    public function createIndex($name, array $options = []): void {}

    public function deleteIndex($name): void {}

    /**
     * @return array{hits: list<array{id: int|string, _score: float}>, total: int}
     */
    private function performSearch(Builder $builder, ?int $perPage, ?int $page): array
    {
        /** @var Model $model */
        $model = $builder->model;
        $connection = $this->resolveConnection($model);

        $this->ensureSearchable($connection, $model->getTable());

        $compiled = $perPage === null
            ? SearchQueryBuilder::forSearch($builder)
            : SearchQueryBuilder::forPaginate($builder, $perPage, (int) $page);

        if (! $compiled instanceof SearchQueryBuilder) {
            return ['hits' => [], 'total' => 0];
        }

        $config = SearchQueryBuilder::resolveConfig($model);

        return $connection->transaction(function () use ($connection, $compiled, $config): array {
            $connection->statement(sprintf(
                'SET LOCAL pg_trgm.similarity_threshold = %F',
                $config['trigram_threshold'],
            ));

            $rows = $connection->select($compiled->sql, $compiled->bindings);

            return [
                'hits' => array_map(
                    fn (object $row): array => [
                        'id' => $row->id,
                        '_score' => (float) $row->_score,
                    ],
                    $rows,
                ),
                'total' => $rows === [] ? 0 : (int) $rows[0]->_total,
            ];
        });
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
