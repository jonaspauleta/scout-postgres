# scout-postgres

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jonaspauleta/scout-postgres.svg?style=flat-square)](https://packagist.org/packages/jonaspauleta/scout-postgres)
[![Tests](https://img.shields.io/github/actions/workflow/status/jonaspauleta/scout-postgres/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jonaspauleta/scout-postgres/actions/workflows/run-tests.yml)
[![Coverage](https://img.shields.io/codecov/c/github/jonaspauleta/scout-postgres?style=flat-square)](https://codecov.io/gh/jonaspauleta/scout-postgres)
[![Total Downloads](https://img.shields.io/packagist/dt/jonaspauleta/scout-postgres.svg?style=flat-square)](https://packagist.org/packages/jonaspauleta/scout-postgres)
[![License](https://img.shields.io/packagist/l/jonaspauleta/scout-postgres.svg?style=flat-square)](LICENSE.md)

A Postgres-native Laravel Scout engine for apps that want search without running Meilisearch, Algolia, or Typesense. Targets the common 80% case when you already run Postgres — no extra service, no sync queue, no separate index. See [Should I use this?](#should-i-use-this), [Comparison](#comparison), and [Limitations](#limitations) for the cases it does **not** cover.

Works on any Postgres 14+ where `pg_trgm` and `unaccent` are available — managed (Neon, Laravel Cloud, RDS, Supabase, DigitalOcean) or self-managed.

## Why

Most Laravel apps already have Postgres. Adding Meilisearch or Typesense means another service to deploy, secure, monitor, scale, and keep in sync. For mid-sized catalogs (millions of rows, sub-100ms p95) Postgres FTS combined with `pg_trgm` similarity is good enough, cheaper, and stays consistent with your source of truth — there is no separate index to drift out of sync.

## Should I use this?

**Use it when:**

- You already run Postgres and don't want to operate a second search service.
- Your corpus is in the hundreds-of-thousands to low-millions of rows.
- You need typo tolerance, prefix / as-you-type matching, and accent-insensitive search.
- You're happy with raw-SQL faceting and aggregation (Scout returns model IDs; you join from there).
- A single `regconfig` (text search configuration) per table is enough — usually `simple_unaccent` or one language per migration.

**Use a dedicated engine instead when:**

- You need first-class faceting, highlighting, or synonym dictionaries.
- You need per-document language switching (English row, Portuguese row, Japanese row in the same table).
- Your corpus is hundreds of millions of rows.
- You need geo-search and don't want to add PostGIS.
- You need learning-to-rank, vector hybrid search, or relevance feedback features.

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

| Key                            | Env                                            | Default                | Notes                                                                                                                                                |
|--------------------------------|------------------------------------------------|------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------|
| `text_search_config`           | `SCOUT_POSTGRES_CONFIG`                        | `simple_unaccent`      | Postgres `regconfig` for indexing + querying.                                                                                                        |
| `fts_weight`                   | `SCOUT_POSTGRES_FTS_WEIGHT`                    | `2.0`                  | Multiplier on the `ts_rank` component of the score.                                                                                                  |
| `trigram_weight`               | `SCOUT_POSTGRES_TRIGRAM_WEIGHT`                | `1.0`                  | Multiplier on the trigram component of the score.                                                                                                    |
| `trigram_threshold`            | `SCOUT_POSTGRES_TRIGRAM_THRESHOLD`             | `0.3`                  | `SET LOCAL pg_trgm.<fn>_threshold` per query. Lower = more recall + more noise. Tune **higher** for long-text corpora — see Production notes.        |
| `trigram_function`             | `SCOUT_POSTGRES_TRIGRAM_FUNCTION`              | `similarity`           | `similarity` / `word_similarity` / `strict_word_similarity`. Picks the operator (`%` / `<%` / `<<%`) and matching threshold variable. See Performance.|
| `rank_function`                | `SCOUT_POSTGRES_RANK_FUNCTION`                 | `ts_rank`              | `ts_rank` (frequency) or `ts_rank_cd` (cover density).                                                                                               |
| `rank_weights`                 | —                                              | `[0.1, 0.2, 0.4, 1.0]` | Multipliers for D / C / B / A columns.                                                                                                               |
| `rank_normalization`           | `SCOUT_POSTGRES_RANK_NORMALIZATION`            | `32`                   | [Postgres rank normalization bitmask](https://www.postgresql.org/docs/current/textsearch-controls.html).                                             |
| `query_strategy`               | `SCOUT_POSTGRES_QUERY_STRATEGY`                | `adaptive`             | `adaptive` (FTS first, hybrid on miss), `hybrid` (single-pass FTS+trigram), `fts_only` (no trigram). See Performance.                                |
| `prefix_fast_path`             | `SCOUT_POSTGRES_PREFIX_FAST_PATH`              | `true`                 | Single short tokens skip `websearch_to_tsquery` and the trigram pass; only `to_tsquery(:prefix:*)` runs.                                             |
| `prefix_fast_path_max_length`  | `SCOUT_POSTGRES_PREFIX_FAST_PATH_MAX_LENGTH`   | `6`                    | Length below which `prefix_fast_path` triggers (exclusive).                                                                                          |
| `disable_jit`                  | `SCOUT_POSTGRES_DISABLE_JIT`                   | `true`                 | Issues `SET LOCAL jit = off` per search transaction. JIT compile cost typically exceeds savings on FTS queries.                                      |
| `total_count`                  | `SCOUT_POSTGRES_TOTAL_COUNT`                   | `false`                | When `true`, every query computes `COUNT(*) OVER()` so paginators can show the full match-set total. Off by default for lower p95 — see Performance. |

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

The engine picks one of three SQL shapes per query, depending on the
configured `query_strategy` and whether the query is a single short token.

### Adaptive (default): FTS-first with trigram fallback

A common single token like `world` or a multi-token phrase that the FTS
tokenizer can match runs only the cheaper FTS query:

```sql
WITH q AS (
  SELECT websearch_to_tsquery('simple_unaccent', :query)        AS ws,
         to_tsquery('simple_unaccent', :prefix_query)           AS pfx
)
SELECT id,
       (CASE WHEN search_vector @@ COALESCE(q.ws || q.pfx, q.ws)
             THEN ts_rank(weights, search_vector, q.ws, normalization)
             ELSE 0 END) * fts_weight AS _score
FROM "tracks", q
WHERE search_vector @@ COALESCE(q.ws || q.pfx, q.ws)
ORDER BY _score DESC, "id" ASC
LIMIT :perPage OFFSET :offset;
```

When the FTS pass returns fewer rows than the requested page, the engine
re-runs the **hybrid** query that adds a trigram-similarity arm so typo and
fuzzy queries still recover:

```sql
WITH q AS (
  SELECT websearch_to_tsquery('simple_unaccent', :query)        AS ws,
         to_tsquery('simple_unaccent', :prefix_query)           AS pfx
)
SELECT id,
       (CASE WHEN search_vector @@ COALESCE(q.ws || q.pfx, q.ws)
             THEN ts_rank(weights, search_vector, q.ws, normalization)
             ELSE 0 END) * fts_weight
       + COALESCE(similarity(search_text, :raw), 0) * trigram_weight AS _score
FROM "tracks", q
WHERE search_vector @@ COALESCE(q.ws || q.pfx, q.ws)
   OR search_text % :raw
ORDER BY _score DESC, "id" ASC
LIMIT :perPage OFFSET :offset;
```

So in the worst case adaptive runs two queries; in the common case it runs
one. `query_strategy=hybrid` forces single-pass FTS+trigram for every
query. `query_strategy=fts_only` never runs the trigram pass — fastest but
loses typo recovery.

### Short-prefix fast path

When the query is a single token shorter than `prefix_fast_path_max_length`
(default 6) and contains no whitespace or quotes, the engine takes a
dedicated path that skips both `websearch_to_tsquery` and the trigram
pass entirely:

```sql
WITH q AS (
  SELECT to_tsquery('simple_unaccent', :prefix_query) AS pfx
)
SELECT id,
       ts_rank(weights, search_vector, q.pfx, normalization) * fts_weight AS _score
FROM "tracks", q
WHERE search_vector @@ q.pfx
ORDER BY _score DESC, "id" ASC
LIMIT :perPage OFFSET :offset;
```

This is the dominant win for as-you-type UIs: short prefixes used to
produce huge candidate sets via the trigram bitmap. Disable with
`SCOUT_POSTGRES_PREFIX_FAST_PATH=false`.

### Common to all paths

- All user input is parameterised (`:query`, `:prefix_query`, `:raw`,
  filter values). Config values (weights, normalisation) are numeric and
  inlined.
- `total_count=true` adds a `COUNT(*) OVER() AS _total` column to whichever
  query runs.
- `disable_jit=true` issues `SET LOCAL jit = off` per transaction — the
  JIT compile cost frequently exceeds savings on FTS queries.

## Performance

scout-postgres is tuned out of the box for the common Scout-driver workload
(short queries, paginated lists, sub-100 ms latency budgets). The defaults
that matter:

- **Adaptive query strategy** (`query_strategy=adaptive`) — runs the cheaper
  FTS-only query first; falls back to FTS+trigram only when FTS recall is
  insufficient. Cuts trigram-bitmap cost on common queries.
- **Short-prefix fast path** (`prefix_fast_path=true`,
  `prefix_fast_path_max_length=6`) — single short tokens like `phil` skip
  both `websearch_to_tsquery` and the trigram pass; only `to_tsquery(:prefix:*)`
  runs. The dominant win for as-you-type UIs.
- **`total_count=false`** — `COUNT(*) OVER()` is omitted by default; latency
  scales with **page size** instead of match-set size. Opt back in
  per-query when an exact total is required.
- **JIT off per query** (`disable_jit=true`) — Postgres' JIT compile cost
  (10–30 ms cold) frequently exceeds savings on FTS queries that themselves
  complete in single-digit ms.
- **`search_text` cap (1000 chars)** — the `postgresSearchable()` migration
  macro wraps the concatenated text in `LEFT(...)` so trigram cost is
  bounded regardless of source-column length.
- **`trigram_function=similarity`** — paired with the default
  `trigram_threshold=0.3`. `word_similarity` and `strict_word_similarity`
  are available as opt-in tunings; their thresholds have different
  semantics so re-tune the threshold when switching.

See [`benchmarks/`](benchmarks/) for measured numbers on 50k and 500k
row corpora, with full methodology and the artisan harness.

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

By default `getTotalCount()` returns the size of the current page only —
the SQL omits `COUNT(*) OVER()` and latency scales with page size rather
than match-set size. Opt back in when you need a precise total:

```php
Book::search('foo')
    ->options(['scout_postgres' => ['total_count' => true]])
    ->paginate(20);
```

Or globally via `SCOUT_POSTGRES_TOTAL_COUNT=true` for every search.

When `total_count` is enabled the window aggregate forces Postgres to
materialise every matching row before applying `LIMIT`, so latency scales
with **match-set size** — measurable on broad-match queries against
million-row corpora.

## Stability

The package is **stable** as of `v1.0.0`. The public API — the `postgresSearchable()` migration macro, the `dropPostgresSearchable()` macro, the `PostgresSearchable` contract, the `pgsql` Scout engine driver name, and the keys in `config/scout-postgres.php` — is committed across the entire `1.x` line. Breaking changes will land on a `2.0.0` release tag and will be documented in `CHANGELOG.md` with a migration note.

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

## FAQ

**Do I need to run `php artisan scout:import`?**
No. The `search_vector` and `search_text` columns are `STORED GENERATED`,
so Postgres recomputes them automatically on every `INSERT` / `UPDATE`.
There is no external index to seed.

**Should I enable `SCOUT_QUEUE=true`?**
No — keep it `false`. Scout's queue exists to debounce writes to a remote
index; this engine has no remote index. Enabling the queue just adds a
no-op job to your worker.

**Why are `update`, `delete`, `flush`, `createIndex`, and `deleteIndex`
no-ops?**
The Postgres generated columns and GIN indexes are the source of truth.
There is no separate index to push to, so Scout's per-row sync hooks have
nothing to do — the engine implements them as no-ops on purpose. Adding
`postgresSearchable()` in a migration is the only "indexing" step.

**What text search configuration does the engine use?**
`simple_unaccent` — a copy of the built-in `simple` config mapped through
the `unaccent` dictionary. The package migration creates it. Override via
`SCOUT_POSTGRES_CONFIG` or per model via `scoutPostgresConfig()`.

**Can different models use different connections?**
Yes. The model's connection driver must be `pgsql`, otherwise the engine
throws `UnsupportedDriverException`. The engine reads the connection from
the model on every query.

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
3. Update `CHANGELOG.md` with an entry describing the change.

## Security

If you discover a security issue, please email **jpaulo4799santos@gmail.com** rather than opening a public issue.

## Credits

- [João Paulo Santos](https://github.com/jonaspauleta)
- [All contributors](https://github.com/jonaspauleta/scout-postgres/contributors)
- Built on top of [Laravel Scout](https://github.com/laravel/scout) and Postgres' excellent `tsvector` / `pg_trgm` machinery.

## License

Released under the MIT License. See [LICENSE.md](LICENSE.md).
