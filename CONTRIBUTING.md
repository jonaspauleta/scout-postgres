# Contributing

Thanks for considering a contribution. This document is short and concrete.

## Workflow

1. Fork and clone the repo.
2. Create a feature branch from `main`: `git checkout -b feat/short-description`.
3. Make your change. **Add or update a Pest test** that fails before your change and passes after.
4. Run the full quality suite locally — it must pass clean:

   ```bash
   composer quality   # rector + pint + phpstan
   composer test      # pest
   ```

5. Update `CHANGELOG.md` under the `[Unreleased]` section using [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) categories (`Added` / `Changed` / `Fixed` / `Deprecated` / `Removed` / `Security`).
6. Commit using [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:` …).
7. Open a PR against `main`. Link any related issue.

## Local environment

You need a running Postgres 14+ with the credentials baked into `phpunit.xml`:

```
host=127.0.0.1  port=5432  db=scout_postgres_test  user=postgres  password=postgres
```

Override per-shell with env vars if your local Postgres differs.

The test suite truncates and recreates schema on each run — do not point it at a database you care about.

## Standards

- PHP 8.3+, `declare(strict_types=1)` on every file.
- `final` classes by default.
- PHPStan level **max**, no baseline. New code must not regress this.
- Pint config in `pint.json` is the source of truth for formatting.
- Rector config in `rector.php` is the source of truth for refactors.
- All user-facing exception messages must tell the user what to do, not just what failed.

## What we will not merge

- Changes that broaden the public API without a clear use case.
- New runtime dependencies without a concrete justification.
- Synonym / stopword features. Use a dedicated search engine if you need those.
- Feature flags or backwards-compat shims that have no migration story.

## Reporting bugs

Open an issue with the bug report template. Include the failing query, the model migration, and the actual + expected behaviour. A failing Pest test in a gist is the fastest path to a fix.

## Security issues

See [SECURITY.md](SECURITY.md). Do not open public issues for security problems.
