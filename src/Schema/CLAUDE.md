# src/Schema/ — Agent Guide

## postgresSearchable macro

Adds two generated columns and two GIN indexes to a table:

- `search_vector tsvector GENERATED ALWAYS AS (
    setweight(to_tsvector('<config>', coalesce("<col>", '')), '<A-D>') ||
    ...
  ) STORED`
- `search_text text GENERATED ALWAYS AS (coalesce("<col>", '') || ' ' || ...) STORED`
  (uses `||` + `coalesce`, not `concat_ws` — Postgres requires IMMUTABLE)
- `<table>_search_vector_gin ON <table> USING gin(search_vector)`
- `<table>_search_text_trgm ON <table> USING gin(search_text gin_trgm_ops)`

## Weight letters

Only A, B, C, D are valid. Any other letter throws `InvalidArgumentException`.
Mapping to `ts_rank` weights is controlled by `config('scout-postgres.rank_weights')`,
ordered D, C, B, A.

## Adding to an existing table

```php
Schema::table('users', function (Blueprint $table): void {
    $table->postgresSearchable(['name' => 'A', 'username' => 'B']);
});
```

On large tables (>1M rows) the `STORED` add rewrites the table. Either split
the migration so each column is added separately with `SET LOCAL lock_timeout`,
or run it during a maintenance window.
