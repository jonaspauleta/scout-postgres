<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Unit;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use ScoutPostgres\Engines\PostgresEngine;
use ScoutPostgres\Exceptions\UnsupportedDriverException;
use ScoutPostgres\Tests\Fixtures\Models\Book;

test('write methods are no-ops', function (): void {
    /** @var PostgresEngine $engine */
    $engine = resolve(PostgresEngine::class);

    /** @var EloquentCollection<int, Model> $empty */
    $empty = new EloquentCollection;

    $engine->update($empty);
    $engine->delete($empty);
    $engine->flush(new Book);
    $engine->createIndex('books');
    $engine->deleteIndex('books');

    expect(true)->toBeTrue();
});

test('search returns hits and total', function (): void {
    Book::factory()->create(['title' => 'Nürburgring Racing']);
    Book::factory()->create(['title' => 'The Green Hell']);

    $results = Book::search('nurburgring')->raw();

    expect($results)->toHaveKey('hits')
        ->and($results)->toHaveKey('total');

    expect(is_array($results) ? $results['total'] : 0)->toBeGreaterThanOrEqual(1);
});

test('mapIds returns primary keys', function (): void {
    $book = Book::factory()->create(['title' => 'Zanzibar']);

    /** @var PostgresEngine $engine */
    $engine = resolve(PostgresEngine::class);

    /** @var Builder<Model> $builder */
    $builder = new Builder(new Book, 'zanzibar');
    $results = $engine->search($builder);

    expect($engine->mapIds($results)->all())->toContain($book->id);
});

test('map preserves score order', function (): void {
    // Title-weighted (A) match must rank above summary-weighted (C) match
    // under any query strategy — the title row is the FTS winner regardless
    // of trigram contribution.
    Book::factory()->create(['title' => 'Random', 'summary' => 'About zanzibar.', 'author' => '']);
    $titleMatch = Book::factory()->create(['title' => 'Zanzibar', 'summary' => 'Random.', 'author' => '']);

    $results = Book::search('zanzibar')->get();

    expect($results->modelKeys())->toContain($titleMatch->id);
    expect($results->first()?->getKey())->toBe($titleMatch->id);
});

test('engine throws on non-pgsql connection', function (): void {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]);

    /** @var Builder<Model> $builder */
    $builder = new Builder(new Book, 'any');

    /** @var PostgresEngine $engine */
    $engine = resolve(PostgresEngine::class);
    $engine->search($builder);
})->throws(UnsupportedDriverException::class);
