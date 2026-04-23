# scout-postgres

Native Postgres 18 full-text search + `pg_trgm` trigram similarity engine for Laravel Scout. Drop-in replacement for Meilisearch on apps running Laravel Cloud (Neon Postgres) or any managed Postgres that allows `CREATE EXTENSION pg_trgm` and `CREATE EXTENSION unaccent`.

## Requirements

- PHP 8.5+
- Laravel 13+
- Laravel Scout 11+
- Postgres 18
- Extensions: `pg_trgm` >= 1.6, `unaccent` >= 1.1

## Install

```bash
composer require jonaspauleta/scout-postgres
php artisan vendor:publish --tag=scout-postgres-config
php artisan migrate
```

## Configure

Set your Scout driver:

```env
SCOUT_DRIVER=pgsql
SCOUT_QUEUE=false
```

`SCOUT_QUEUE=false` is recommended — generated columns compute on write, so there is nothing to queue.

## Make a model searchable

```php
Schema::table('tracks', function (Blueprint $table): void {
    $table->postgresSearchable(['name' => 'A', 'city' => 'B', 'country' => 'C']);
});
```

On the model:

```php
use Laravel\Scout\Searchable;

final class Track extends Model
{
    use Searchable;

    protected $hidden = ['search_vector', 'search_text'];
}
```

Scout's `toSearchableArray()` is not required — the engine reads directly from the generated columns.

## Query

```php
Track::search('nurb')->take(5)->get();
Track::search('spa')->where('active', true)->paginate(20);
```

Soft deletes, `->where()` with operators, `->whereIn()`, `->orderBy()`, `->paginate()`, `->cursor()` all supported.

## Per-model overrides

```php
use ApexScout\ScoutPostgres\Contracts\PostgresSearchable;

final class Track extends Model implements PostgresSearchable
{
    public function scoutPostgresConfig(): array
    {
        return ['trigram_threshold' => 0.25];
    }
}
```

## Configuration options

See `config/scout-postgres.php`. Every option is environment-overridable.

## License

MIT.
