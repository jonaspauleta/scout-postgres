<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Feature;

use ApexScout\ScoutPostgres\Tests\Fixtures\Models\Book;

test('matches exact title', function (): void {
    Book::factory()->create(['title' => 'Nürburgring Racing']);
    Book::factory()->create(['title' => 'Spa Francorchamps']);

    $results = Book::search('Nürburgring')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()?->title)->toBe('Nürburgring Racing');
});

test('matches accent-insensitive', function (): void {
    Book::factory()->create(['title' => 'São Paulo']);

    $results = Book::search('sao paulo')->get();

    expect($results)->toHaveCount(1);
});

test('matches prefix', function (): void {
    Book::factory()->create(['title' => 'jonaspauleta notes', 'author' => '', 'summary' => '']);

    $results = Book::search('jonas')->get();

    expect($results)->toHaveCount(1);
});

test('empty query returns no results without hitting the DB', function (): void {
    Book::factory()->count(3)->create();

    $results = Book::search('')->get();

    expect($results)->toHaveCount(0);
});

test('returns empty collection when nothing matches', function (): void {
    Book::factory()->create(['title' => 'zzz']);

    $results = Book::search('completely-unrelated-xyz')->get();

    expect($results)->toHaveCount(0);
});
