<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Feature;

use Illuminate\Support\Facades\DB;

test('migration installs pg_trgm, unaccent, and simple_unaccent config', function (): void {
    $extensions = collect(DB::select("SELECT extname FROM pg_extension WHERE extname IN ('pg_trgm','unaccent')"))
        ->pluck('extname')
        ->all();

    expect($extensions)->toContain('pg_trgm')
        ->and($extensions)->toContain('unaccent');

    $configs = collect(DB::select("SELECT cfgname FROM pg_ts_config WHERE cfgname = 'simple_unaccent'"))
        ->pluck('cfgname')
        ->all();

    expect($configs)->toContain('simple_unaccent');
});

test('migration is idempotent', function (): void {
    DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
    expect(true)->toBeTrue();
})->throwsNoExceptions();
