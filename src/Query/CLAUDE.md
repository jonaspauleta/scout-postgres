# src/Query/ — Agent Guide

## SQL shape (current)

```
WITH q AS (SELECT websearch_to_tsquery(...) ws, to_tsquery(...:* ) pfx)
SELECT id, weighted_score AS _score, COUNT(*) OVER() AS _total
FROM "<table>", q
WHERE (search_vector @@ COALESCE(q.ws || q.pfx, q.ws) OR search_text % :raw_trgm) + user wheres
ORDER BY _score DESC, "<pk>" ASC
LIMIT ... OFFSET ...
```

`||` in the `COALESCE(q.ws || q.pfx, q.ws)` expression is tsquery OR (not
string concat), so matches pass if either the websearch query OR the prefix
query matches. An earlier revision used `&&` (AND); that excluded single-token
prefix queries from matching multi-token lexemes and was fixed in commit
`a024be00`.

## Binding discipline

- Every user-derived value is parameterised (`:query`, `:prefix_query`, `:raw`,
  `:raw_trgm`, `where_*`, `in_*`, `notin_*`).
- Never concatenate user input into SQL. Config values (weights, normalisation)
  are numeric and sourced from config — inlined is acceptable.
- **PDO_PGSQL with native prepares rewrites each `:name` occurrence into a
  separate `$n` placeholder.** Any named placeholder that must appear more
  than once in the SQL needs a distinct binding per occurrence. See the
  `:raw` / `:raw_trgm` duplication.

## Adding a query feature

1. Add a test in `tests/Unit/SearchQueryBuilderTest.php` asserting the SQL
   contains the new clause.
2. Add the compile method to `SearchQueryBuilder`. Bindings go into the shared
   `&$bindings` reference.
3. Do not break pagination — the window function `COUNT(*) OVER()` reads total
   from the first row; changes must preserve this.

## Adding a new tokeniser / query strategy

Websearch syntax covers v1. If a future consumer needs phrase-only or
boolean-only queries, expose a Scout `Builder` macro (`->usingPhraseQuery()`)
that sets `$builder->options['query_method']` and branch on it in
`SearchQueryBuilder::build()`. Do not swap the default without a feature flag.
