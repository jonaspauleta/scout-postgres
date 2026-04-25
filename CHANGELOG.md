# Changelog

All notable changes to `jonaspauleta/scout-postgres` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/jonaspauleta/scout-postgres/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.4.0
[0.3.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.3.0
[0.2.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.2.0
[0.1.1]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.1.1
[0.1.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.1.0
