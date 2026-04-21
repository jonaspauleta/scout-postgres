<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Feature;

use ApexScout\ScoutPostgres\Tests\Fixtures\Models\Book;

test('paginate returns a LengthAwarePaginator with correct total', function (): void {
    for ($i = 0; $i < 25; $i++) {
        Book::factory()->create(['title' => "match {$i}", 'author' => '', 'summary' => '']);
    }

    $page1 = Book::search('match')->paginate(10, 'page', 1);
    $page3 = Book::search('match')->paginate(10, 'page', 3);

    expect($page1->total())->toBe(25)
        ->and($page1->count())->toBe(10)
        ->and($page3->count())->toBe(5);
});

test('take limits non-paginated results', function (): void {
    for ($i = 0; $i < 15; $i++) {
        Book::factory()->create(['title' => "match {$i}", 'author' => '', 'summary' => '']);
    }

    $results = Book::search('match')->take(3)->get();

    expect($results)->toHaveCount(3);
});
