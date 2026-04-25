<?php

declare(strict_types=1);

namespace ScoutPostgres\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use RuntimeException;
use ScoutPostgres\Query\SearchQueryBuilder;
use ScoutPostgres\Tests\Fixtures\Models\Book;

/**
 * @param  array<string, array<int, mixed>>  $options
 * @return Builder<Model>
 */
function makeBuilder(string $query, array $options = []): Builder
{
    /** @var Builder<Model> $builder */
    $builder = new Builder(new Book, $query);
    foreach ($options as $method => $args) {
        $result = $builder->{$method}(...$args);
        if ($result instanceof Builder) {
            $builder = $result;
        }
    }

    return $builder;
}

/**
 * @param  array<string, array<int, mixed>>  $options
 */
function compile(string $query, array $options = []): SearchQueryBuilder
{
    $result = SearchQueryBuilder::forSearch(makeBuilder($query, $options));
    if (! $result instanceof SearchQueryBuilder) {
        throw new RuntimeException('SearchQueryBuilder::forSearch returned null');
    }

    return $result;
}

test('empty query yields no SQL (engine short-circuits before reaching builder)', function (): void {
    $result = SearchQueryBuilder::forSearch(makeBuilder(''));

    expect($result)->toBeNull();
});

test('canonical query produces parameterised SQL', function (): void {
    $result = compile('jon');

    expect($result->sql)->toContain('FROM "books"')
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
    $result = compile('jon', ['where' => ['status', 'active']]);

    expect($result->sql)->toContain('AND "status" = ');
});

test('wheres support comparison operators', function (): void {
    $result = compile('jon', ['where' => ['price', '>', 100]]);

    expect($result->sql)->toContain('AND "price" > ');
});

test('whereIns become IN clauses', function (): void {
    $result = compile('jon', ['whereIn' => ['status', ['a', 'b']]]);

    expect($result->sql)->toContain('AND "status" IN (');
});

test('__soft_deleted=0 translates to IS NULL', function (): void {
    $result = compile('jon', ['where' => ['__soft_deleted', 0]]);

    expect($result->sql)->toContain('"deleted_at" IS NULL')
        ->and($result->sql)->not->toContain('__soft_deleted');
});

test('__soft_deleted=1 translates to IS NOT NULL', function (): void {
    $result = compile('jon', ['where' => ['__soft_deleted', 1]]);

    expect($result->sql)->toContain('"deleted_at" IS NOT NULL');
});

test('explicit orders override default _score DESC', function (): void {
    $result = compile('jon', ['orderBy' => ['title', 'asc']]);

    expect($result->sql)->toContain('ORDER BY "title" asc')
        ->and($result->sql)->not->toContain('ORDER BY _score DESC');
});

test('pagination adds LIMIT and OFFSET', function (): void {
    $result = SearchQueryBuilder::forPaginate(makeBuilder('jon'), perPage: 20, page: 3);
    if (! $result instanceof SearchQueryBuilder) {
        throw new RuntimeException('SearchQueryBuilder::forPaginate returned null');
    }

    expect($result->sql)->toContain('LIMIT 20')
        ->and($result->sql)->toContain('OFFSET 40');
});

test('total_count=false omits COUNT(*) OVER from SQL', function (): void {
    $result = compile('jon', [
        'options' => [['scout_postgres' => ['total_count' => false]]],
    ]);

    expect($result->sql)->not->toContain('COUNT(*) OVER()');
});

test('total_count=true keeps COUNT(*) OVER in SQL', function (): void {
    $result = compile('jon', [
        'options' => [['scout_postgres' => ['total_count' => true]]],
    ]);

    expect($result->sql)->toContain('COUNT(*) OVER()');
});

test('unrecognised scout_postgres option keys leave totals enabled by default', function (): void {
    $result = compile('jon', [
        'options' => [['scout_postgres' => ['unknown_key' => 'value']]],
    ]);

    expect($result->sql)->toContain('COUNT(*) OVER()');
});
