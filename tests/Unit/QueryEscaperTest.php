<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Tests\Unit;

use ApexScout\ScoutPostgres\Query\QueryEscaper;

test('escapes tsquery special characters', function (string $input, string $expected): void {
    expect(QueryEscaper::escapeForTsquery($input))->toBe($expected);
})->with([
    ['hello', 'hello'],
    ['hello world', 'hello world'],
    ["it's & me", 'its me'],           // apostrophes dropped; & removed
    ['a|b', 'ab'],
    ['(a) & !b:*', 'a b'],
    ['   ', ''],                       // whitespace-only → empty
]);

test('builds prefix query string from last token', function (string $input, string $expected): void {
    expect(QueryEscaper::buildPrefixQuery($input))->toBe($expected);
})->with([
    ['jon', 'jon:*'],
    ['jon silva', 'jon & silva:*'],
    ['jon   silva', 'jon & silva:*'],
    ['', ''],
]);

test('normalises raw query for trigram use', function (string $input, string $expected): void {
    expect(QueryEscaper::normaliseForTrigram($input))->toBe($expected);
})->with([
    ['Nürburgring', 'nurburgring'],
    ['São Paulo', 'sao paulo'],
    ['  HELLO  world ', 'hello world'],
]);
