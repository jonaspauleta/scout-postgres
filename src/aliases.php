<?php

declare(strict_types=1);
use ScoutPostgres\Contracts\PostgresSearchable;
use ScoutPostgres\Engines\PostgresEngine;
use ScoutPostgres\Exceptions\MissingPostgresExtensionException;
use ScoutPostgres\Exceptions\ModelNotSearchableException;
use ScoutPostgres\Exceptions\UnsupportedDriverException;
use ScoutPostgres\Query\QueryEscaper;
use ScoutPostgres\Query\SearchQueryBuilder;
use ScoutPostgres\Schema\Blueprint;
use ScoutPostgres\ScoutPostgresServiceProvider;

/*
 * Backwards-compatibility aliases for the pre-1.1 namespace
 * `ApexScout\ScoutPostgres\` → `ScoutPostgres\`.
 *
 * The package's root namespace was renamed in 1.1.0. Existing consumers may
 * still import classes via the legacy namespace; these `class_alias()` calls
 * keep that working across the entire 1.x line. The legacy namespace will be
 * removed in 2.0 — migrate your imports to `ScoutPostgres\…` to silence the
 * IDE deprecation hint and prepare for the cut-over.
 */

/** @var array<string, string> $aliases */
$aliases = [
    ScoutPostgresServiceProvider::class => ApexScout\ScoutPostgres\ScoutPostgresServiceProvider::class,
    PostgresEngine::class => ApexScout\ScoutPostgres\Engines\PostgresEngine::class,
    SearchQueryBuilder::class => ApexScout\ScoutPostgres\Query\SearchQueryBuilder::class,
    QueryEscaper::class => ApexScout\ScoutPostgres\Query\QueryEscaper::class,
    Blueprint::class => ApexScout\ScoutPostgres\Schema\Blueprint::class,
    PostgresSearchable::class => ApexScout\ScoutPostgres\Contracts\PostgresSearchable::class,
    MissingPostgresExtensionException::class => ApexScout\ScoutPostgres\Exceptions\MissingPostgresExtensionException::class,
    ModelNotSearchableException::class => ApexScout\ScoutPostgres\Exceptions\ModelNotSearchableException::class,
    UnsupportedDriverException::class => ApexScout\ScoutPostgres\Exceptions\UnsupportedDriverException::class,
];

foreach ($aliases as $real => $legacy) {
    if (! class_exists($legacy, false) && ! interface_exists($legacy, false)) {
        class_alias($real, $legacy);
    }
}
