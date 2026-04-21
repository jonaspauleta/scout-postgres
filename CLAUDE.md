# scout-postgres — Agent Guide

Native Postgres 18 FTS + pg_trgm engine for Laravel Scout. Lives under
`packages/apex-scout/scout-postgres` in the ApexScout repo.

## Installation

- `CREATE EXTENSION pg_trgm` and `CREATE EXTENSION unaccent` must succeed.
  On Neon/Laravel Cloud the default DB role has permission.
- Run the package migration: `php artisan migrate` — picked up automatically via `runsMigrations()`. No `--path=` flag required.
- Add per-table generated columns via `$table->postgresSearchable([...])` in an
  app migration.
- Set `SCOUT_DRIVER=pgsql`, `SCOUT_QUEUE=false`.

## Common pitfalls

- **`simple_unaccent` config must exist before `postgresSearchable()` migrations
  run** — the package migration creates it. Run package migrations first.
- **`STORED` generated columns rewrite the table** on add. For tables with >1M
  rows, split the migration or run with `lock_timeout` set.
- **Neon database branches** do not retroactively inherit extensions or text
  search configs — re-run the extensions migration per branch.
- **The `search_text` expression uses `||` + `coalesce`, not `concat_ws`** —
  `concat_ws` is STABLE, not IMMUTABLE, so Postgres rejects it inside STORED
  generated column definitions.

## Do not

- Add Meilisearch-style synonyms / stopwords tuning to v1. Deferred.
- Hardcode `deleted_at` filters in `SearchQueryBuilder` — Scout injects
  `__soft_deleted` into `$builder->wheres` when `scout.soft_delete=true`.
- Register the macro anywhere other than `ScoutPostgresServiceProvider::packageBooted()`.
