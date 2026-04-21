<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Unit;

use ApexScout\ScoutPostgres\Engines\PostgresEngine;
use ApexScout\ScoutPostgres\Exceptions\UnsupportedDriverException;
use ApexScout\ScoutPostgres\Tests\Fixtures\Models\Book;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

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
    // Fix author/summary to empty so the trigram similarity signal is dominated
    // by the title alone; otherwise random faker copy drowns the score delta.
    Book::factory()->create(['title' => 'Zanzibar Zebra', 'author' => '', 'summary' => '']);
    $b2 = Book::factory()->create(['title' => 'Zanzibar', 'author' => '', 'summary' => '']);

    $results = Book::search('zanzibar')->get();

    // Exact match should rank above prefix-only.
    expect($results->modelKeys())->toContain($b2->id);
    expect($results->first()?->getKey())->toBe($b2->id);
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
