# Changelog

All notable changes to `jonaspauleta/scout-postgres` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-04-25

First public stable release.

### Engine

- Postgres-native Scout driver (`pgsql`) backed by `tsvector` full-text
  search and `pg_trgm` trigram similarity, combined into a single hybrid
  `_score` per row.
- Three query strategies, selectable per model or globally via
  `SCOUT_POSTGRES_QUERY_STRATEGY`:
  - `adaptive` (default) — run an FTS-only query first; fall back to the
    hybrid FTS + trigram query only when FTS recall is insufficient.
  - `hybrid` — single-pass FTS + trigram on every query.
  - `fts_only` — never use trigram. Loses typo tolerance, cuts trigram
    bitmap cost on every query.
- Short-prefix fast path (`prefix_fast_path=true`,
  `prefix_fast_path_max_length=6`): single short tokens skip
  `websearch_to_tsquery` and the trigram pass entirely; only
  `to_tsquery(:prefix:*)` runs. Dominant win for as-you-type UIs.
- `total_count` config (default `false`). When false, `getTotalCount()`
  returns the size of the current page, dropping `COUNT(*) OVER()` and
  scaling latency with page size instead of match-set size. Per-query
  opt-in via `->options(['scout_postgres' => ['total_count' => true]])`.
- `disable_jit` config (default `true`). Each search transaction issues
  `SET LOCAL jit = off` so the JIT compile cost cannot dominate FTS
  queries that complete in single-digit ms.
- `trigram_function` config (`similarity` / `word_similarity` /
  `strict_word_similarity`). Default `similarity`. Selects both the
  scoring expression and the operator (`%` / `<%` / `<<%`); the
  per-query `SET LOCAL` threshold variable is derived to match.
- `trigram_threshold` default `0.3`, tuned for typical mixed-length
  corpora (titles, authors, short descriptions). Long-text corpora
  should tune higher; short-text corpora can lower it.

### Schema

- `Schema::table()->postgresSearchable([...])` macro adds two `STORED
  GENERATED` columns (`search_vector`, `search_text`) plus matching GIN
  indexes (one on the tsvector, one on the text with `gin_trgm_ops`).
- `search_text` is wrapped in `LEFT(..., 1000)` by default so trigram
  cost is bounded regardless of source-column length. Pass a custom cap
  or `0` (no cap) as the third macro argument.
- `dropPostgresSearchable()` macro removes both columns and indexes.
- Package migration creates the `pg_trgm` and `unaccent` extensions and
  the `simple_unaccent` text search configuration. Loaded automatically;
  no `--path=` flag required.

### Per-model overrides

- `PostgresSearchable` contract lets a single model override any
  `config/scout-postgres.php` key via `scoutPostgresConfig()`.

### Query support

- Scout-native: `Model::search()`, `where()`, `whereIn()`, `whereNotIn()`,
  `orderBy()`, `paginate()`, `cursor()`, soft deletes
  (`scout.soft_delete=true`).

### Compatibility

- PHP 8.3 / 8.4 / 8.5
- Laravel 11 / 12 / 13
- Laravel Scout 10 / 11
- Postgres 14+ (CI-tested on Postgres 18) with `pg_trgm` ≥ 1.6 and
  `unaccent` ≥ 1.1.

[1.0.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v1.0.0
