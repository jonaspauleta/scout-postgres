<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Unit;

use ApexScout\ScoutPostgres\ScoutPostgresServiceProvider;

test('service provider boots without error', function (): void {
    expect(app()->getProviders(ScoutPostgresServiceProvider::class))->not->toBeEmpty();
});
