<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Contracts;

/**
 * Implement on Eloquent models to override engine defaults per-model.
 *
 * Every method is optional; the engine treats the contract as opt-in.
 */
interface PostgresSearchable
{
    /**
     * Return a partial config array to merge on top of the global config.
     *
     * Recognised keys match config/scout-postgres.php:
     *   fts_weight, trigram_weight, trigram_threshold, rank_function,
     *   rank_weights, rank_normalization, text_search_config, sync.
     *
     * @return array<string, mixed>
     */
    public function scoutPostgresConfig(): array;
}
