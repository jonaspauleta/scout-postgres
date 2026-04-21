# src/ — Agent Guide

## Class map

- `PostgresEngine` — Scout `Engine` contract. 11 methods: 5 are no-op
  (update/delete/flush/createIndex/deleteIndex), 6 delegate to
  `SearchQueryBuilder` + hydrate models.
- `Query/SearchQueryBuilder` — Pure translator `Scout\Builder` → SQL + bindings.
- `Query/QueryEscaper` — String helpers for `to_tsquery` safety and trigram input.
- `Schema/Blueprint` — `$table->postgresSearchable()` + `dropPostgresSearchable()`.
- `Contracts/PostgresSearchable` — Opt-in per-model config override.
- `Exceptions/*` — Every failure surfaces here with an actionable message.

## Scout Builder shape note

Scout 11 stores `$builder->wheres` as a **list** of assoc arrays:
`[['field' => ..., 'operator' => ..., 'value' => ...], ...]`. Do not treat it
as a keyed map. The 2-arg `->where($field, $value)` form is normalised by
Scout into `['field' => ..., 'operator' => '=', 'value' => ...]` before it
reaches the engine, so you never need to special-case it yourself.

## When adding an Engine method

If a new Scout `Engine` method becomes abstract in a future release:
1. Add a test in `tests/Unit/PostgresEngineTest.php` first.
2. Implement in `PostgresEngine` following the delegation pattern.
3. Never add SQL directly in the engine — route via `SearchQueryBuilder`.
