<?php

declare(strict_types=1);

namespace ScoutPostgres\Contracts;

/**
 * Implement on Eloquent models to override engine defaults per-model.
 *
 * The contract itself is opt-in — only models that need to deviate from the
 * package config implement it. Models that omit the interface use the global
 * config from `config/scout-postgres.php` as-is.
 */
interface PostgresSearchable
{
    /**
     * Return a partial config array to merge on top of the global config.
     *
     * Recognised keys match config/scout-postgres.php:
     *   fts_weight, trigram_weight, trigram_threshold, rank_function,
     *   rank_weights, rank_normalization, text_search_config.
     *
     * Unrecognised keys are ignored. Any subset is accepted; unset keys fall
     * back to `config('scout-postgres.*')`.
     *
     * @return array<string, mixed>
     */
    public function scoutPostgresConfig(): array;
}
