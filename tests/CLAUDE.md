# tests/ — Agent Guide

## Boot

Tests extend `ScoutPostgres\Tests\TestCase`, which wires up
Orchestra Testbench + the `pgsql` connection defined by env vars.
SQLite is **not** supported — the feature set (tsvector, gin_trgm_ops,
STORED generated columns) requires a real Postgres 18.

## Writing a search assertion

```php
Book::factory()->create(['title' => 'Foo', 'author' => '', 'summary' => '']);
expect(Book::search('Foo')->get())->toHaveCount(1);
```

Use `Book::search('...')->raw()` to inspect `['hits', 'total']` directly.

Clear `author`/`summary` in ranking/pagination tests — Faker noise in the
`search_text` column drags trigram similarity for the test query and can
flip ordering or drop matches unexpectedly.

## Fixtures

- `Fixtures/Models/Book.php` — the canonical searchable model.
- `Fixtures/database/migrations/` — load order: `create_books_table` then
  `add_postgres_search_to_books`. The package's own extensions migration
  runs first via the base `TestCase::defineDatabaseMigrations()`.

## Running a single test

```bash
vendor/bin/pest --filter='matches exact title'
```
