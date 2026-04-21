<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Unit;

use ApexScout\ScoutPostgres\Engines\PostgresEngine;
use ApexScout\ScoutPostgres\Exceptions\UnsupportedDriverException;
use ApexScout\ScoutPostgres\Tests\Fixtures\Models\Book;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;

test('write methods are no-ops', function (): void {
    $engine = app(PostgresEngine::class);

    $engine->update(Collection::make([]));
    $engine->delete(Collection::make([]));
    $engine->flush(new Book());
    $engine->createIndex('books');
    $engine->deleteIndex('books');

    expect(true)->toBeTrue();
});

test('search returns hits and total', function (): void {
    Book::factory()->create(['title' => 'Nürburgring Racing']);
    Book::factory()->create(['title' => 'The Green Hell']);

    $results = Book::search('nurburgring')->raw();

    expect($results)->toHaveKey('hits')
        ->and($results)->toHaveKey('total')
        ->and($results['total'])->toBeGreaterThanOrEqual(1);
});

test('mapIds returns primary keys', function (): void {
    $book = Book::factory()->create(['title' => 'Zanzibar']);

    $engine = app(PostgresEngine::class);
    $builder = new Builder(new Book(), 'zanzibar');
    $results = $engine->search($builder);

    expect($engine->mapIds($results)->all())->toContain($book->id);
});

test('map preserves score order', function (): void {
    $b1 = Book::factory()->create(['title' => 'Zanzibar Zebra']);
    $b2 = Book::factory()->create(['title' => 'Zanzibar']);

    $results = Book::search('zanzibar')->get();

    // Exact match should rank above prefix-only.
    expect($results->first()->id)->toBe($b2->id);
});

test('engine throws on non-pgsql connection', function (): void {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]);

    app(PostgresEngine::class)->search(new Builder(new Book(), 'any'));
})->throws(UnsupportedDriverException::class);
