# scout-postgres

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jonaspauleta/scout-postgres.svg?style=flat-square)](https://packagist.org/packages/jonaspauleta/scout-postgres)
[![Tests](https://img.shields.io/github/actions/workflow/status/jonaspauleta/scout-postgres/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jonaspauleta/scout-postgres/actions/workflows/run-tests.yml)
[![Coverage](https://img.shields.io/codecov/c/github/jonaspauleta/scout-postgres?style=flat-square)](https://codecov.io/gh/jonaspauleta/scout-postgres)
[![Total Downloads](https://img.shields.io/packagist/dt/jonaspauleta/scout-postgres.svg?style=flat-square)](https://packagist.org/packages/jonaspauleta/scout-postgres)
[![License](https://img.shields.io/packagist/l/jonaspauleta/scout-postgres.svg?style=flat-square)](LICENSE.md)

Postgres-native search engine for [Laravel Scout](https://laravel.com/docs/scout). Removes the need for Meilisearch / Algolia / Typesense for the common 80% case when you already run Postgres — no extra service, no sync queue, no separate index. See [Comparison](#comparison) and [Limitations](#limitations) for the cases it does **not** cover.

Works on any Postgres 14+ where `pg_trgm` and `unaccent` are available — managed (Neon, Laravel Cloud, RDS, Supabase, DigitalOcean) or self-managed.

## Why

Most Laravel apps already have Postgres. Adding Meilisearch or Typesense means another service to deploy, secure, monitor, scale, and keep in sync. For mid-sized catalogs (millions of rows, sub-100ms p95) Postgres FTS combined with `pg_trgm` similarity is good enough, cheaper, and stays consistent with your source of truth — there is no separate index to drift out of sync.

## Comparison

Honest side-by-side. Use this to decide whether `scout-postgres` is the right tool, or whether you actually need a dedicated engine.

| Capability                       | scout-postgres                              | Meilisearch                | Algolia                | Typesense              |
|----------------------------------|---------------------------------------------|----------------------------|------------------------|------------------------|
| Separate service to operate      | ❌ no — runs inside Postgres                | ✅ yes                     | ✅ yes (managed)       | ✅ yes                 |
| Sync mechanism                   | None — `STORED GENERATED` columns           | Application pushes via API | Application pushes via API | Application pushes via API |
| Typo tolerance                   | ✅ via `pg_trgm` similarity                 | ✅ built-in                | ✅ built-in            | ✅ built-in            |
| Prefix / as-you-type matching    | ✅ via `to_tsquery(:*)`                     | ✅ built-in                | ✅ built-in            | ✅ built-in            |
| Faceting / aggregations          | ⚠ via raw SQL; no facet API                | ✅ first-class             | ✅ first-class         | ✅ first-class         |
| Highlighting / snippets          | ⚠ via `ts_headline`; not auto-wired         | ✅ built-in                | ✅ built-in            | ✅ built-in            |
| Multi-language per row           | ❌ one `regconfig` per migration            | ✅ per-document             | ✅ per-document         | ✅ per-document         |
| Synonyms                         | ❌ defer to your `regconfig`                | ✅ first-class             | ✅ first-class         | ✅ first-class         |
| Geo-search                       | ⚠ via PostGIS, not this package             | ✅ built-in                | ✅ built-in            | ✅ built-in            |
| Best-fit catalogue size          | ~hundreds → low millions of rows            | tens of millions           | hundreds of millions   | hundreds of millions   |
| Cost model                       | Free — just Postgres CPU + storage          | Self-host or managed plan  | Per-record + per-search pricing | Self-host or Cloud plan |

If you need faceting, highlighting wired by default, synonyms, multi-language-per-row, or hundreds of millions of records → use a dedicated engine. If you can live with raw-SQL faceting, no auto-snippets, and a single `regconfig` → keep it in Postgres and ship faster.

## Features

- **Hybrid scoring** — `ts_rank` over `tsvector` *plus* `pg_trgm` similarity, weighted and combined into a single `_score`.
- **Prefix matching** — partial words match as you type (`nurb` → `nürburgring`).
- **Accent-insensitive** — `simple_unaccent` text search config strips diacritics on both sides.
- **Typo tolerance** — trigram similarity catches misspellings.
- **No sync** — `STORED GENERATED` columns recompute on `INSERT` / `UPDATE`. Nothing to queue, nothing to drift.
- **GIN-indexed** — one GIN index on the `tsvector`, one on the `text` (with `gin_trgm_ops`). Searches run in tens of ms on millions of rows.
- **Per-column weights (A / B / C / D)** — boost titles over descriptions, names over bios.
- **Scout-native** — `Model::search()`, `where()`, `whereIn()`, `whereNotIn()`, `orderBy()`, `paginate()`, `cursor()`, soft deletes — all supported.
- **Per-model overrides** — tune trigram threshold or weights per searchable model.

## Requirements

| Component       | Version                                                       |
|-----------------|---------------------------------------------------------------|
| PHP             | 8.3, 8.4, 8.5                                                 |
| Laravel         | 11.x, 12.x, 13.x                                              |
| Laravel Scout   | 10.x, 11.x                                                    |
| Postgres        | 14+ (CI-tested on **Postgres 18**)                            |
| Extensions      | `pg_trgm` ≥ 1.6, `unaccent` ≥ 1.1                             |

> On managed Postgres (Neon, Laravel Cloud, Supabase, RDS, …) the default database owner role is usually authorised to run `CREATE EXTENSION pg_trgm` / `unaccent`. On self-managed Postgres you may need a superuser to install them once per database.

## Installation

```bash
composer require jonaspauleta/scout-postgres
```

Publish the config (optional — defaults are sane):

```bash
php artisan vendor:publish --tag=scout-postgres-config
```

Run migrations — the package migration is auto-loaded and creates the `pg_trgm` + `unaccent` extensions plus the `simple_unaccent` text search configuration:

```bash
php artisan migrate
```

## Configuration

Set Scout to use the `pgsql` engine in your `.env`:

```env
SCOUT_DRIVER=pgsql
SCOUT_QUEUE=false
```

`SCOUT_QUEUE=false` is recommended — there is nothing to queue. The `search_vector` and `search_text` columns are `STORED GENERATED` and recompute automatically on every write.

### Configuration reference

All values are environment-overridable. See `config/scout-postgres.php`.

| Key                         | Env                                  | Default                | Notes                                                                                                          |
|-----------------------------|--------------------------------------|------------------------|----------------------------------------------------------------------------------------------------------------|
| `text_search_config`        | `SCOUT_POSTGRES_CONFIG`              | `simple_unaccent`      | Postgres `regconfig` for indexing + querying.                                                                  |
| `fts_weight`                | `SCOUT_POSTGRES_FTS_WEIGHT`          | `2.0`                  | Multiplier on the `ts_rank` component of the score.                                                            |
| `trigram_weight`            | `SCOUT_POSTGRES_TRIGRAM_WEIGHT`      | `1.0`                  | Multiplier on the `similarity()` component of the score.                                                       |
| `trigram_threshold`         | `SCOUT_POSTGRES_TRIGRAM_THRESHOLD`   | `0.3`                  | `SET LOCAL pg_trgm.similarity_threshold` per query. Lower = more recall + more noise. Tune **higher** for long-text corpora — see Production notes. |
| `rank_function`             | `SCOUT_POSTGRES_RANK_FUNCTION`       | `ts_rank`              | `ts_rank` (frequency) or `ts_rank_cd` (cover density).                                                         |
| `rank_weights`              | —                                    | `[0.1, 0.2, 0.4, 1.0]` | Multipliers for D / C / B / A columns.                                                                         |
| `rank_normalization`        | `SCOUT_POSTGRES_RANK_NORMALIZATION`  | `32`                   | [Postgres rank normalization bitmask](https://www.postgresql.org/docs/current/textsearch-controls.html).       |

## Make a model searchable

### 1. Add the generated columns + indexes via migration

Use the `postgresSearchable()` macro on the table blueprint. Pass each column you want indexed with a weight letter (A = highest, D = lowest):

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table): void {
            $table->postgresSearchable([
                'name'    => 'A',
                'city'    => 'B',
                'country' => 'C',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table): void {
            $table->dropPostgresSearchable();
        });
    }
};
```

This adds:

- `search_vector tsvector GENERATED ALWAYS AS (...) STORED` — weighted `to_tsvector` over the listed columns.
- `search_text text GENERATED ALWAYS AS (...) STORED` — same columns concatenated, used for trigram matching.
- A GIN index on `search_vector`.
- A GIN index on `search_text` with `gin_trgm_ops`.

### 2. Add the `Searchable` trait to your model

```php
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

final class Track extends Model
{
    use Searchable;

    protected $hidden = ['search_vector', 'search_text'];
}
```

You do **not** need to implement `toSearchableArray()` — the engine reads the generated columns directly from the table.

## Querying

```php
// Basic search.
Track::search('nurb')->take(5)->get();

// Filtered + paginated.
Track::search('spa')->where('active', true)->paginate(20);

// Multi-value + sorted.
Track::search('eifel')
    ->whereIn('country', ['de', 'be', 'nl'])
    ->orderBy('length_km', 'desc')
    ->get();

// Lazy iteration.
Track::search('long')->cursor()->each(fn ($track) => /* ... */);
```

### Soft deletes

To exclude soft-deleted rows from search, enable Scout's built-in soft-delete handling:

```php
// config/scout.php
'soft_delete' => true,
```

The engine respects Scout's `__soft_deleted` filter and translates it into `deleted_at IS NULL` / `IS NOT NULL`. Use `->withTrashed()` / `->onlyTrashed()` on the Scout builder as usual.

## Per-model overrides

Implement `PostgresSearchable` to override config on a single model — useful when one model needs stricter fuzzy matching, a different rank function, or its own text search config:

```php
use ScoutPostgres\Contracts\PostgresSearchable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

final class Track extends Model implements PostgresSearchable
{
    use Searchable;

    public function scoutPostgresConfig(): array
    {
        return [
            'trigram_threshold' => 0.25,
            'fts_weight'        => 3.0,
            'trigram_weight'    => 0.5,
        ];
    }
}
```

Any subset of the global config keys is accepted. Unset keys fall back to `config('scout-postgres.*')`.

## How it works

For each search Scout runs a single query roughly shaped as:

```sql
WITH q AS (
  SELECT websearch_to_tsquery('simple_unaccent', :query)        AS ws,
         to_tsquery('simple_unaccent', :prefix_query)           AS pfx
)
SELECT id,
       (CASE WHEN search_vector @@ COALESCE(q.ws || q.pfx, q.ws)
             THEN ts_rank(weights, search_vector, q.ws, normalization)
             ELSE 0 END) * fts_weight
       + COALESCE(similarity(search_text, :raw), 0) * trigram_weight AS _score,
       COUNT(*) OVER() AS _total
FROM "tracks", q
WHERE search_vector @@ COALESCE(q.ws || q.pfx, q.ws)
   OR search_text % :raw
ORDER BY _score DESC, "id" ASC
LIMIT :perPage OFFSET :offset;
```

The hybrid `WHERE` clause means a row matches if **either** the websearch tsquery (with prefix expansion of the last token) **or** the trigram similarity passes. Each row is scored once; pagination uses a window function so the total count comes back without a second round-trip.

All user input is parameterised. Config values (weights, normalisation) are numeric and inlined.

## Production notes

### Large tables

Adding a `STORED GENERATED` column **rewrites the entire table**. On tables with millions of rows, do not run `postgresSearchable()` in a normal deploy migration. Either:

- Split the migration so each generated column is added in a separate transaction, with `SET LOCAL lock_timeout = '5s'` and a retry loop, or
- Run it during a maintenance window.

### Database branching (Neon, copy-on-write forks)

Neon branches and similar copy-on-write forks do **not** retroactively inherit extensions or text search configurations from their parent — only data. Re-run `php artisan migrate` against each new branch (or `CREATE EXTENSION` / the `simple_unaccent` config manually).

### Multi-connection setups

If your searchable models live on a non-default connection, that connection's driver must be `pgsql`. The engine throws `UnsupportedDriverException` otherwise.

### Tuning `trigram_threshold` per corpus

The default `trigram_threshold = 0.3` is tuned for typical mixed-length corpora — titles, authors, short descriptions. **Lower it only when you know your corpus is short text**, otherwise the trigram bitmap explodes the candidate set and recheck cost dominates p95 latency.

A concrete example from the included benchmark (50,000 rows of `title + subtitle + author + multi-paragraph summary`):

| query              | `threshold = 0.15` | `threshold = 0.3` |
|--------------------|-------------------:|------------------:|
| `modern history`   |          1264 ms   |          186 ms   |
| `philosophical exposition` | 1584 ms   |           83 ms   |
| `phil`             |          1429 ms   |          599 ms   |

Override per model when one model needs different tuning:

```php
public function scoutPostgresConfig(): array
{
    return ['trigram_threshold' => 0.45]; // long-text article body
}
```

### Total-count cost — `COUNT(*) OVER()`

By default every search query computes `COUNT(*) OVER()` so `getTotalCount()` reflects the size of the full match set, not just the current page. The trade-off is real: the window aggregate forces Postgres to materialise every matching row before applying `LIMIT`, so latency scales with **match-set size**, not page size.

If you do not need a precise total — for example, "show 20 results, link to next page" — opt out per query:

```php
Book::search('foo')
    ->options(['scout_postgres' => ['total_count' => false]])
    ->paginate(20);
```

When opted out, `getTotalCount()` returns the size of the current page rather than the full match set; in exchange, latency drops to roughly the cost of fetching the top `N` rows.

## Stability

The package is **stable** as of `v1.0.0`. The public API — the `postgresSearchable()` migration macro, the `dropPostgresSearchable()` macro, the `PostgresSearchable` contract, the `pgsql` Scout engine driver name, and the keys in `config/scout-postgres.php` — is committed across the entire `1.x` line. Breaking changes will land on a `2.0.0` release tag and will be documented in `CHANGELOG.md` with a migration note.

The legacy PHP namespace `ApexScout\ScoutPostgres\` is preserved as `class_alias` shims through `1.x` and will be removed in `2.0`.

## Limitations

By design, this package targets the 80% case. The following are **not** in scope for the `1.x` line:

- No synonym / stopword customisation beyond what your `regconfig` provides.
- No relevance feedback / learning-to-rank.
- No multi-language per-row text search config switching (one `regconfig` per migration).
- No federated multi-table search — search one model at a time.

If you need any of the above, you probably need a dedicated search engine.

## Troubleshooting

**`MissingPostgresExtensionException: pg_trgm is not installed`**
The DB role running migrations cannot create the extension. On managed Postgres use the database owner role. Self-managed: have a superuser run `CREATE EXTENSION pg_trgm; CREATE EXTENSION unaccent;` once per database.

**`ModelNotSearchableException: Table "X" has no "search_vector" column`**
You forgot to call `$table->postgresSearchable([...])` in a migration for that table.

**`UnsupportedDriverException: requires a pgsql connection`**
The model's connection driver is not `pgsql`. Check `config/database.php` and the model's `$connection`.

**`text search configuration "simple_unaccent" does not exist`**
The package migration did not run. Run `php artisan migrate`. The package's own migration is loaded automatically — no `--path` flag is needed.

## Testing

```bash
composer test          # Pest
composer analyse       # PHPStan level max
composer format        # Pint
composer quality       # rector + pint + phpstan
```

The test suite spins up a Postgres 18 service in CI and exercises the full SQL pipeline end-to-end. Locally you'll need a running Postgres 14+ with the credentials in `phpunit.xml`.

## Contributing

Pull requests are welcome. For substantial changes please open an issue first to discuss what you'd like to change.

When sending a PR:

1. Add a Pest test that fails before your change and passes after.
2. Run `composer quality` — it must pass clean (Pint, Rector, PHPStan level max).
3. Update `CHANGELOG.md` under the `[Unreleased]` section.

## Security

If you discover a security issue, please email **jpaulo4799santos@gmail.com** rather than opening a public issue.

## Credits

- [João Paulo Santos](https://github.com/jonaspauleta)
- [All contributors](https://github.com/jonaspauleta/scout-postgres/contributors)
- Built on top of [Laravel Scout](https://github.com/laravel/scout) and Postgres' excellent `tsvector` / `pg_trgm` machinery.

## License

Released under the MIT License. See [LICENSE.md](LICENSE.md).
