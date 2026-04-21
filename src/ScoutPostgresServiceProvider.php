<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres;

use ApexScout\ScoutPostgres\Engines\PostgresEngine;
use ApexScout\ScoutPostgres\Schema\Blueprint as BlueprintMacros;
use Laravel\Scout\EngineManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ScoutPostgresServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('scout-postgres')
            ->hasConfigFile('scout-postgres')
            ->hasMigration('create_postgres_search_extensions');
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
