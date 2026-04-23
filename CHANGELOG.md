# Changelog

All notable changes to `jonaspauleta/scout-postgres` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/jonaspauleta/scout-postgres/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.3.0
[0.2.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.2.0
[0.1.1]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.1.1
[0.1.0]: https://github.com/jonaspauleta/scout-postgres/releases/tag/v0.1.0
