<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function generationExpressionFor(string $column): string
{
    $rows = DB::select(
        "SELECT generation_expression FROM information_schema.columns
         WHERE table_name = 'books' AND column_name = ?",
        [$column],
    );

    if ($rows === []) {
        return '';
    }

    $row = $rows[0];
    if (! is_object($row) || ! property_exists($row, 'generation_expression')) {
        return '';
    }

    $value = $row->generation_expression;

    return is_string($value) ? $value : '';
}

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

test('search_text generated column applies the default LEFT cap', function (): void {
    $expr = generationExpressionFor('search_text');

    expect($expr)->toContain('"left"(')
        ->and($expr)->toContain('1000');
});

test('search_vector generated column is NOT capped', function (): void {
    $expr = generationExpressionFor('search_vector');

    expect($expr)->not->toContain('"left"(')
        ->and($expr)->toContain('to_tsvector');
});
