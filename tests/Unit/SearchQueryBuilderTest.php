<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Unit;

use ApexScout\ScoutPostgres\Query\SearchQueryBuilder;
use ApexScout\ScoutPostgres\Tests\Fixtures\Models\Book;
use Laravel\Scout\Builder;

function makeBuilder(string $query, array $options = []): Builder
{
    $builder = new Builder(new Book, $query);
    foreach ($options as $method => $args) {
        $builder = $builder->{$method}(...$args);
    }

    return $builder;
}

test('empty query yields no SQL (engine short-circuits before reaching builder)', function (): void {
    $result = SearchQueryBuilder::forSearch(makeBuilder(''));

    expect($result)->toBeNull();
});

test('canonical query produces parameterised SQL', function (): void {
    $result = SearchQueryBuilder::forSearch(makeBuilder('jon'));

    expect($result)->not->toBeNull()
        ->and($result->sql)->toContain('FROM "books"')
        ->and($result->sql)->toContain('websearch_to_tsquery')
        ->and($result->sql)->toContain('search_vector')
        ->and($result->sql)->toContain('search_text')
        ->and($result->sql)->toContain('COUNT(*) OVER()')
        ->and($result->sql)->toContain('ORDER BY _score DESC');

    expect($result->bindings)
        ->toHaveKey('query')
        ->toHaveKey('prefix_query')
        ->toHaveKey('raw');
});

test('wheres become parameterised equality filters', function (): void {
    $builder = makeBuilder('jon', ['where' => ['status', 'active']]);
    $result = SearchQueryBuilder::forSearch($builder);

    expect($result->sql)->toContain('AND "status" = ');
});

test('wheres support comparison operators', function (): void {
    $builder = makeBuilder('jon', ['where' => ['price', '>', 100]]);
    $result = SearchQueryBuilder::forSearch($builder);

    expect($result->sql)->toContain('AND "price" > ');
});

test('whereIns become IN clauses', function (): void {
    $builder = makeBuilder('jon', ['whereIn' => ['status', ['a', 'b']]]);
    $result = SearchQueryBuilder::forSearch($builder);

    expect($result->sql)->toContain('AND "status" IN (');
});

test('__soft_deleted=0 translates to IS NULL', function (): void {
    $builder = makeBuilder('jon', ['where' => ['__soft_deleted', 0]]);
    $result = SearchQueryBuilder::forSearch($builder);

    expect($result->sql)->toContain('"deleted_at" IS NULL')
        ->and($result->sql)->not->toContain('__soft_deleted');
});

test('__soft_deleted=1 translates to IS NOT NULL', function (): void {
    $builder = makeBuilder('jon', ['where' => ['__soft_deleted', 1]]);
    $result = SearchQueryBuilder::forSearch($builder);

    expect($result->sql)->toContain('"deleted_at" IS NOT NULL');
});

test('explicit orders override default _score DESC', function (): void {
    $builder = makeBuilder('jon', ['orderBy' => ['title', 'asc']]);
    $result = SearchQueryBuilder::forSearch($builder);

    expect($result->sql)->toContain('ORDER BY "title" asc')
        ->and($result->sql)->not->toContain('ORDER BY _score DESC');
});

test('pagination adds LIMIT and OFFSET', function (): void {
    $result = SearchQueryBuilder::forPaginate(makeBuilder('jon'), perPage: 20, page: 3);

    expect($result->sql)->toContain('LIMIT 20')
        ->and($result->sql)->toContain('OFFSET 40');
});
