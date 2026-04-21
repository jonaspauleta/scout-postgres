<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Feature;

use ApexScout\ScoutPostgres\Tests\Fixtures\Models\Book;

test('title matches rank above summary matches', function (): void {
    $summaryMatch = Book::factory()->create([
        'title' => 'Random',
        'summary' => 'This is about tulips.',
        'author' => '',
    ]);
    $titleMatch = Book::factory()->create([
        'title' => 'Tulips',
        'summary' => 'A random summary.',
        'author' => '',
    ]);

    $results = Book::search('tulips')->get();

    expect($results->first()?->getKey())->toBe($titleMatch->id);
    expect($results->last()?->getKey())->toBe($summaryMatch->id);
});

test('exact match ranks above prefix-only', function (): void {
    Book::factory()->create(['title' => 'Tulipanomania', 'author' => '', 'summary' => '']);
    $exact = Book::factory()->create(['title' => 'Tulip', 'author' => '', 'summary' => '']);

    $results = Book::search('Tulip')->get();

    expect($results->first()?->getKey())->toBe($exact->id);
});
