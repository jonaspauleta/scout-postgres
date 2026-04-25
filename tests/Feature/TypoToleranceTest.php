<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Feature;

use ScoutPostgres\Tests\Fixtures\Models\Book;

test('single-character typo is recovered via trigram', function (): void {
    Book::factory()->create(['title' => 'jonaspauleta', 'author' => '', 'summary' => '']);

    $results = Book::search('jonapauleta')->get();   // missing 's'

    expect($results)->toHaveCount(1);
});

test('below-threshold typo is excluded', function (): void {
    Book::factory()->create(['title' => 'Zanzibar', 'author' => '', 'summary' => '']);

    $results = Book::search('xyzzyx')->get();

    expect($results)->toHaveCount(0);
});

test('per-model threshold override is honoured', function (): void {
    // default threshold 0.3; override to 0.9 → stricter.
    config()->set('scout-postgres.trigram_threshold', 0.9);

    Book::factory()->create(['title' => 'jonaspauleta', 'author' => '', 'summary' => '']);
    $results = Book::search('jonapauleta')->get();

    expect($results)->toHaveCount(0);
});
