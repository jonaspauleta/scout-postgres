<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('postgresSearchable adds search_vector and search_text generated columns', function (): void {
    expect(Schema::hasColumn('books', 'search_vector'))->toBeTrue()
        ->and(Schema::hasColumn('books', 'search_text'))->toBeTrue();
});

test('generated columns populate on insert', function (): void {
    DB::table('books')->insert([
        'title' => 'Nürburgring',
        'author' => 'Ada',
        'summary' => 'A novel.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('books')->select('search_vector', 'search_text')->first();

    expect($row)->not->toBeNull();
    $searchText = is_object($row) && isset($row->search_text) && is_string($row->search_text) ? $row->search_text : '';
    $searchVector = is_object($row) && isset($row->search_vector) && (is_string($row->search_vector) || is_scalar($row->search_vector)) ? (string) $row->search_vector : '';

    expect($searchText)->toContain('Nürburgring');
    expect($searchVector)->toContain('nurburgring'); // unaccented
});

test('GIN indexes exist for both search columns', function (): void {
    $indexes = collect(DB::select(
        "SELECT indexname FROM pg_indexes WHERE tablename = 'books' AND indexname LIKE 'books_search%'"
    ))->pluck('indexname')->all();

    expect($indexes)->toContain('books_search_vector_gin')
        ->and($indexes)->toContain('books_search_text_trgm');
});
