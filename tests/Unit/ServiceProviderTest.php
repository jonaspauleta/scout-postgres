<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Unit;

use ScoutPostgres\ScoutPostgresServiceProvider;

test('service provider boots without error', function (): void {
    expect(app()->getProviders(ScoutPostgresServiceProvider::class))->not->toBeEmpty();
});
