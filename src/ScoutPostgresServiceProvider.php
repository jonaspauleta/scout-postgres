<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres;

use ApexScout\ScoutPostgres\Schema\Blueprint as BlueprintMacros;
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
    }
}
