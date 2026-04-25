<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Feature;

use ScoutPostgres\Tests\Fixtures\Models\Book;

test('adaptive strategy returns FTS-only results when recall is sufficient', function (): void {
    config()->set('scout-postgres.query_strategy', 'adaptive');

    Book::factory()->count(3)->create(['title' => 'Spa Francorchamps']);

    $results = Book::search('Spa Francorchamps')->get();

    expect($results->count())->toBe(3);
});

test('adaptive strategy falls back to hybrid for typo queries', function (): void {
    config()->set('scout-postgres.query_strategy', 'adaptive');

    Book::factory()->create(['title' => 'jonaspauleta', 'author' => '', 'summary' => '']);

    // FTS won't match "nurburgring" misspelled badly enough to defeat the
    // simple_unaccent stemming, so push it harder — single-letter typo on a
    // long token is the canonical trigram-recovery case.
    $results = Book::search('jonapauleta')->get();

    expect($results->count())->toBe(1);
});

test('hybrid strategy reproduces single-pass FTS+trigram behaviour', function (): void {
    config()->set('scout-postgres.query_strategy', 'hybrid');

    Book::factory()->create(['title' => 'jonaspauleta', 'author' => '', 'summary' => '']);

    $results = Book::search('jonapauleta')->get();

    expect($results->count())->toBe(1);
});

test('fts_only strategy excludes typo matches that need trigram', function (): void {
    config()->set('scout-postgres.query_strategy', 'fts_only');

    Book::factory()->create(['title' => 'jonaspauleta', 'author' => '', 'summary' => '']);

    $results = Book::search('jonapauleta')->get();

    // Without trigram fallback, a deep typo no longer recovers.
    expect($results->count())->toBe(0);
});

test('prefix fast path matches a short single token', function (): void {
    config()->set('scout-postgres.prefix_fast_path', true);

    Book::factory()->create(['title' => 'Philosophy', 'author' => '', 'summary' => '']);

    $results = Book::search('phil')->get();

    expect($results->count())->toBe(1);
});

test('disabling prefix_fast_path falls back to adaptive', function (): void {
    config()->set('scout-postgres.prefix_fast_path', false);
    config()->set('scout-postgres.query_strategy', 'adaptive');

    Book::factory()->create(['title' => 'Philosophy', 'author' => '', 'summary' => '']);

    $results = Book::search('phil')->get();

    expect($results->count())->toBe(1);
});

test('adaptive strategy applies user filters across both passes', function (): void {
    config()->set('scout-postgres.query_strategy', 'adaptive');

    Book::factory()->create(['title' => 'jonaspauleta', 'author' => 'Keep', 'summary' => '']);
    Book::factory()->create(['title' => 'jonaspauleta', 'author' => 'Drop', 'summary' => '']);

    // Typo forces hybrid fallback; the where filter must apply on the
    // fallback query too.
    $results = Book::search('jonapauleta')->where('author', 'Keep')->get();

    expect($results->count())->toBe(1);
});
