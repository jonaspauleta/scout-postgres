<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Feature;

use ScoutPostgres\Tests\Fixtures\Models\Book;

beforeEach(function (): void {
    config()->set('scout.soft_delete', true);
});

test('soft deleted records are excluded by default', function (): void {
    $book = Book::factory()->create(['title' => 'Doomed', 'author' => '', 'summary' => '']);
    $book->delete();

    $results = Book::search('Doomed')->get();

    expect($results)->toHaveCount(0);
});

test('withTrashed includes soft-deleted records', function (): void {
    $book = Book::factory()->create(['title' => 'Doomed', 'author' => '', 'summary' => '']);
    $book->delete();

    $results = Book::search('Doomed')->withTrashed()->get();

    expect($results)->toHaveCount(1);
});

test('onlyTrashed returns only soft-deleted', function (): void {
    Book::factory()->create(['title' => 'Alive', 'author' => '', 'summary' => '']);
    $dead = Book::factory()->create(['title' => 'Dead', 'author' => '', 'summary' => '']);
    $dead->delete();

    $results = Book::search('Dead')->onlyTrashed()->get();

    expect($results)->toHaveCount(1);
});
