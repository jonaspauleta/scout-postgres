# Changelog

All notable changes to `jonaspauleta/scout-postgres` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`trigram_function` config (`similarity` / `word_similarity` / `strict_word_similarity`).**
  Default is `word_similarity`. The function controls both the scoring
  expression (`<fn>(search_text, :raw)`) and the WHERE-clause operator (`%`,
  `<%`, `<<%`); the per-query `SET LOCAL` threshold variable name is derived
  to match. `word_similarity` is materially cheaper than the legacy
  `similarity` on long-text columns (multi-paragraph summaries) because it
  only considers words near the query rather than overlapping the whole
  string. Override per model via `scoutPostgresConfig()`.
- **`query_strategy` config (`adaptive` / `hybrid` / `fts_only`).** Default is
  `adaptive`: the engine first runs an FTS-only query and only falls back to
  the hybrid FTS + trigram query when FTS recall is insufficient. The fallback
  preserves typo tolerance for queries that need it (single-character typos,
  fuzzy phrasing) while skipping the expensive trigram bitmap on common
  queries. Override per model via `scoutPostgresConfig()` or globally via the
  `SCOUT_POSTGRES_QUERY_STRATEGY` env var. Set `hybrid` to reproduce the
  pre-1.0 single-pass behaviour.

### Changed

- **Default `trigram_function` is now `word_similarity`** (was effectively
  `similarity` in 1.0.0). On long-text corpora the new default cuts the
  trigram cost without measurable recall loss; on short titles the two are
  near-identical. Set `SCOUT_POSTGRES_TRIGRAM_FUNCTION=similarity` to restore
  the previous behaviour.
- **Default search behaviour now runs FTS first, trigram on fallback.** The
  previous default (`hybrid`) ran both signals in a single pass on every
  query, paying the trigram cost even when FTS already had enough recall.
  Users who relied on the trigram tie-break in score ordering may see ranking
  differences on queries where multiple rows have identical FTS rank — set
  `SCOUT_POSTGRES_QUERY_STRATEGY=hybrid` to restore the old ordering.

- **Root PHP namespace renamed: `ApexScout\ScoutPostgres\` → `ScoutPostgres\`.**
  The `ApexScout` prefix originated from a sister SaaS project and never
  belonged on a standalone OSS package. The legacy namespace is preserved as
  `class_alias` shims (see `src/aliases.php`) so existing `use ApexScout\ScoutPostgres\…`
  imports keep working unchanged for the entire `1.x` line. **Migrate your
  imports to `ScoutPostgres\…` before `2.0`** — the legacy namespace will be
  dropped there.

## [1.0.0] - 2026-04-25

### Added

- **`total_count` per-query option.** Pass
  `->options(['scout_postgres' => ['total_count' => false]])` to skip the
  `COUNT(*) OVER()` window aggregate. Latency drops to roughly the cost of
  fetching the top `N` rows; in exchange, `getTotalCount()` reflects only
  the size of the current page rather than the full match set.
- **Benchmark harness** under `benchmarks/`: methodology, vendored artisan
  command source, and a results table comparing `scout-postgres` against
  Scout's `database` driver across seven query shapes on 50,150 rows.
- README "Production notes" section now covers `trigram_threshold` tuning
  per corpus and the `total_count` opt-out, with concrete latency numbers
  taken from the benchmark.

### Changed

- **Default `trigram_threshold` raised from `0.15` to `0.3`.** The previous
  default optimised for very short text (titles, names); on long-text
  corpora it produced trigram-bitmap candidate sets so large that `p95`
  latency hit the seconds. The new default is safer for typical mixed-length
  corpora; see `Production notes` for tuning guidance and benchmark numbers.
  - **Migration:** if you indexed only short fields and want the previous
    fuzzy-recall behaviour, set
    `SCOUT_POSTGRES_TRIGRAM_THRESHOLD=0.15` or override per model via
    `scoutPostgresConfig()`.

### Stability

- This is the first release tagged as **stable** under SemVer.
  `scout-postgres` is now committed to a non-breaking public API across the
  `1.x` line. Configuration keys, the `PostgresSearchable` contract, the
  `postgresSearchable()` schema macro, and the engine driver name (`pgsql`)
  are all part of the API surface.

## [0.4.1] - 2026-04-25

### Fixed

- **Runtime failure on PHP 8.3.** `Query/QueryEscaper` and `Query/SearchQueryBuilder` used `mb_trim()`, which is PHP 8.4+. The package declared `php ^8.3` in `composer.json` but would fatal at runtime on 8.3. Replaced with `trim()` — the call sites already collapse multi-byte whitespace via `preg_replace('/\s+/', ' ', …)` so plain `trim()` is sufficient.
- **PHPStan failures on Laravel 11 + PHP 8.3/8.4.** Test fixtures used `#[Hidden]` / `#[Table]` (Laravel 12+) and `#[Override]` on properties (PHP 8.5+). Reverted to `protected $hidden` / `protected $table` and dropped the `#[Override]` attributes so the fixtures compile and analyse cleanly across the full support matrix.

### Changed

- Rector now skips `tests/Fixtures` so the `LARAVEL_CODE_QUALITY` set does not re-introduce Laravel 12+ attributes into the fixtures.

## [0.4.0] - 2026-04-25

### Added

- Repository hygiene: `CODE_OF_CONDUCT.md`, GitHub issue / PR templates, Dependabot config.
- `support` block in `composer.json` (issues / source / docs URLs).
- CI matrix across PHP 8.3 / 8.4 / 8.5 × Laravel 11 / 12 / 13 with locked Postgres 18 service.
- Least-privilege `permissions: contents: read` on the test workflow.

### Changed

- **Widened version support:** `php ^8.3` (was `^8.5`), `illuminate/* ^11.0 || ^12.0 || ^13.0` (was `^13.0`), `laravel/scout ^10.0 || ^11.0` (was `^11.0`). Verified by CI matrix.
- README requirements table reflects the new support matrix; positioning softened to cover any managed or self-managed Postgres rather than naming Neon / Laravel Cloud first.
- `.gitattributes` now `export-ignore`s internal agent guides (`CLAUDE.md`), tooling configs, and build artefacts so the Packagist tarball ships only runtime assets.
- `CONTRIBUTING.md` PHP minimum corrected to 8.3+ (was wrongly stated as 8.5+).
- `PostgresSearchable` contract docblock cleaned up: dropped stale `sync` key reference and clarified that the contract (not the method) is opt-in.
- Maintainer contact email updated across `composer.json`, `LICENSE.md`, `SECURITY.md`, and `README.md`.

### Removed

- Dead `InvalidSearchQueryException` class — never thrown from the engine.
- Dead `sync` config key (and `SCOUT_POSTGRES_SYNC` env binding). The engine has nothing to sync; STORED generated columns recompute on write. Use Scout's own `config/scout.php` `'soft_delete'` for soft-delete handling.

## [0.3.0] - 2026-04-23

### Changed

- **Composer vendor renamed:** `apex-scout/scout-postgres` → `jonaspauleta/scout-postgres`. Update consumers' `composer.json` `require` block. The `ApexScout\ScoutPostgres\` PHP namespace is unchanged.
- Initial Packagist release. Previously distributed via VCS repository entry only.

### Migration

```diff
-    "apex-scout/scout-postgres": "^0.2.0"
+    "jonaspauleta/scout-postgres": "^0.3.0"
```

If your `composer.json` declared a VCS repo for this package, remove that block — Packagist now serves it.

## [0.2.0] - 2026

### Added

- PHPStan level `max` enforcement with typed Scout builder generics and config shapes.

## [0.1.1]

### Fixed

- Migration filename prefix to ensure deterministic ordering.

## [0.1.0]

### Added

- Initial release: Postgres 18 full-text search + `pg_trgm` engine for Laravel Scout.

[Unreleased]: https://github.com/jonaspauleta/scout-postgres/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v1.0.0
[0.4.1]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.4.1
[0.4.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.4.0
[0.3.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.3.0
[0.2.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.2.0
[0.1.1]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.1.1
[0.1.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.1.0
