<?php

declare(strict_types=1);

namespace ScoutPostgres;

use Laravel\Scout\EngineManager;
use ScoutPostgres\Engines\PostgresEngine;
use ScoutPostgres\Schema\Blueprint as BlueprintMacros;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ScoutPostgresServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('scout-postgres')
            ->hasConfigFile('scout-postgres')
            ->hasMigration('0000_01_01_000000_create_postgres_search_extensions')
            ->runsMigrations();
    }

    public function packageBooted(): void
    {
        BlueprintMacros::register();

        $app = $this->app;

        $app->resolving(EngineManager::class, function (EngineManager $manager) use ($app): void {
            $manager->extend('pgsql', fn () => $app->make(PostgresEngine::class));
        });
    }
}
