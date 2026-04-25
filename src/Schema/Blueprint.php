<?php

declare(strict_types=1);

namespace ScoutPostgres\Schema;

use Illuminate\Database\Schema\Blueprint as LaravelBlueprint;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Blueprint
{
    public static function register(): void
    {
        LaravelBlueprint::macro('postgresSearchable', function (array $weights, ?string $config = null, int $maxLength = 1000): void {
            /** @var LaravelBlueprint $this */
            Blueprint::apply($this, $weights, $config, $maxLength);
        });

        LaravelBlueprint::macro('dropPostgresSearchable', function (): void {
            /** @var LaravelBlueprint $this */
            Blueprint::drop($this);
        });
    }

    /**
     * @param  array<int|string, mixed>  $weights
     * @param  int  $maxLength  Cap on `search_text` byte length. `0` disables the cap.
     *                          The `search_vector` column is never capped — `to_tsvector`
     *                          deduplicates lexemes so its length is naturally bounded.
     */
    public static function apply(LaravelBlueprint $blueprint, array $weights, ?string $config, int $maxLength = 1000): void
    {
        $normalised = self::validateWeights($weights);

        if ($config === null) {
            $configured = config('scout-postgres.text_search_config', 'simple_unaccent');
            $config = is_string($configured) ? $configured : 'simple_unaccent';
        }

        $table = $blueprint->getTable();
        $vectorExpr = self::buildVectorExpression($normalised, $config);
        $textExpr = self::buildTextExpression(array_keys($normalised), $maxLength);

        DB::statement(sprintf(
            'ALTER TABLE %s ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (%s) STORED',
            self::quote($table),
            $vectorExpr,
        ));

        DB::statement(sprintf(
            'ALTER TABLE %s ADD COLUMN search_text text GENERATED ALWAYS AS (%s) STORED',
            self::quote($table),
            $textExpr,
        ));

        DB::statement(sprintf(
            'CREATE INDEX IF NOT EXISTS %s ON %s USING gin(search_vector)',
            self::quote($table.'_search_vector_gin'),
            self::quote($table),
        ));

        DB::statement(sprintf(
            'CREATE INDEX IF NOT EXISTS %s ON %s USING gin(search_text gin_trgm_ops)',
            self::quote($table.'_search_text_trgm'),
            self::quote($table),
        ));
    }

    public static function drop(LaravelBlueprint $blueprint): void
    {
        $table = $blueprint->getTable();

        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::quote($table.'_search_vector_gin')));
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::quote($table.'_search_text_trgm')));
        DB::statement(sprintf('ALTER TABLE %s DROP COLUMN IF EXISTS search_vector', self::quote($table)));
        DB::statement(sprintf('ALTER TABLE %s DROP COLUMN IF EXISTS search_text', self::quote($table)));
    }

    /**
     * @param  array<int|string, mixed>  $weights
     * @return array<string, string>
     */
    private static function validateWeights(array $weights): array
    {
        throw_if($weights === [], InvalidArgumentException::class, 'postgresSearchable() requires at least one column => weight entry.');

        $valid = ['A', 'B', 'C', 'D'];
        $normalised = [];
        foreach ($weights as $column => $weight) {
            if (! is_string($column) || ! is_string($weight)) {
                throw new InvalidArgumentException(
                    'postgresSearchable() requires string column => string weight entries.',
                );
            }

            if (! in_array($weight, $valid, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Weight for column "%s" must be one of A, B, C, D (got "%s").',
                    $column,
                    $weight,
                ));
            }

            $normalised[$column] = $weight;
        }

        return $normalised;
    }

    /**
     * @param  array<string, string>  $weights
     */
    private static function buildVectorExpression(array $weights, string $config): string
    {
        $parts = [];
        foreach ($weights as $column => $weight) {
            $parts[] = sprintf(
                "setweight(to_tsvector('%s', coalesce(%s, '')), '%s')",
                $config,
                self::quote($column),
                $weight,
            );
        }

        return implode(' || ', $parts);
    }

    /**
     * @param  list<string>  $columns
     * @param  int  $maxLength  Cap on the final concatenated text. `0` disables the cap.
     */
    private static function buildTextExpression(array $columns, int $maxLength): string
    {
        // Use `coalesce` + `||` instead of `concat_ws` because STORED generated
        // columns require an IMMUTABLE expression; `concat_ws` is only STABLE.
        $parts = array_map(
            fn (string $col): string => sprintf("coalesce(%s, '')", self::quote($col)),
            $columns,
        );

        $expr = implode(" || ' ' || ", $parts);

        return $maxLength > 0
            ? sprintf('LEFT(%s, %d)', $expr, $maxLength)
            : $expr;
    }

    private static function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
