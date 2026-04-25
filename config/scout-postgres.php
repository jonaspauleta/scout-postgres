<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Text Search Configuration
    |--------------------------------------------------------------------------
    |
    | Postgres regconfig used for both indexing (generated column) and querying
    | (websearch_to_tsquery). The package migration creates "simple_unaccent"
    | by default — a copy of "simple" mapped through the unaccent dictionary.
    */
    'text_search_config' => env('SCOUT_POSTGRES_CONFIG', 'simple_unaccent'),

    /*
    |--------------------------------------------------------------------------
    | Score Weights
    |--------------------------------------------------------------------------
    |
    | Final score = ts_rank * fts_weight + similarity * trigram_weight.
    */
    'fts_weight' => (float) env('SCOUT_POSTGRES_FTS_WEIGHT', 2.0),
    'trigram_weight' => (float) env('SCOUT_POSTGRES_TRIGRAM_WEIGHT', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Trigram Similarity Threshold
    |--------------------------------------------------------------------------
    |
    | Applied per-transaction via SET LOCAL pg_trgm.<fn>_threshold (the
    | threshold variable name follows the chosen `trigram_function`).
    | Lower = more recall, more noise. Higher = stricter fuzzy matching.
    |
    | The default 0.3 is tuned for typical mixed-length corpora (titles,
    | authors, short descriptions). On long-text corpora (multi-paragraph
    | summaries, full articles), a lower threshold (e.g. 0.15) explodes the
    | trigram-bitmap candidate set and pushes p95 latency into the seconds.
    | Tune per model via `scoutPostgresConfig()` if your text is long.
    */
    'trigram_threshold' => (float) env('SCOUT_POSTGRES_TRIGRAM_THRESHOLD', 0.3),

    /*
    |--------------------------------------------------------------------------
    | Trigram Function
    |--------------------------------------------------------------------------
    |
    | Selects the pg_trgm function and operator used for fuzzy matching:
    |
    |   "similarity"             — operator `%`,  whole-string overlap
    |   "word_similarity"        — operator `<%`, considers only words near
    |                              the query; cheaper on long-text corpora
    |   "strict_word_similarity" — operator `<<%`, requires extent boundaries
    |                              to align (most selective)
    |
    | Default is `word_similarity` because it is materially faster on
    | long-text columns (multi-paragraph summaries) where whole-string
    | similarity wastes work on irrelevant tail content.
    */
    'trigram_function' => env('SCOUT_POSTGRES_TRIGRAM_FUNCTION', 'word_similarity'),

    /*
    |--------------------------------------------------------------------------
    | Ranking Function
    |--------------------------------------------------------------------------
    |
    | "ts_rank" — frequency-based. "ts_rank_cd" — cover density.
    */
    'rank_function' => env('SCOUT_POSTGRES_RANK_FUNCTION', 'ts_rank'),

    /*
    |--------------------------------------------------------------------------
    | Weight Multipliers (D, C, B, A)
    |--------------------------------------------------------------------------
    */
    'rank_weights' => [0.1, 0.2, 0.4, 1.0],

    /*
    |--------------------------------------------------------------------------
    | Normalization Bitmask
    |--------------------------------------------------------------------------
    |
    | See Postgres docs: https://www.postgresql.org/docs/current/textsearch-controls.html
    */
    'rank_normalization' => (int) env('SCOUT_POSTGRES_RANK_NORMALIZATION', 32),

    /*
    |--------------------------------------------------------------------------
    | Query Strategy
    |--------------------------------------------------------------------------
    |
    | "adaptive" — run an FTS-only query first, fall back to the hybrid
    |              FTS+trigram query only when FTS recall is insufficient.
    |              Cheapest on common queries; same recall as "hybrid" on
    |              typo / fuzzy queries via fallback.
    | "hybrid"   — always run the FTS+trigram query in a single pass. The
    |              pre-1.0 behaviour. Use to reproduce historical timings.
    | "fts_only" — never use trigram. Loses typo tolerance but cuts the
    |              trigram-bitmap candidate-set cost on every query.
    */
    'query_strategy' => env('SCOUT_POSTGRES_QUERY_STRATEGY', 'adaptive'),
];
