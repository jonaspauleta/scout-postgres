<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Schema;

use Illuminate\Database\Schema\Blueprint as LaravelBlueprint;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Blueprint
{
    public static function register(): void
    {
        LaravelBlueprint::macro('postgresSearchable', function (array $weights, ?string $config = null): void {
            /** @var LaravelBlueprint $this */
            Blueprint::apply($this, $weights, $config);
        });

        LaravelBlueprint::macro('dropPostgresSearchable', function (): void {
            /** @var LaravelBlueprint $this */
            Blueprint::drop($this);
        });
    }

    /**
     * @param  array<string, string>  $weights
     */
    public static function apply(LaravelBlueprint $blueprint, array $weights, ?string $config): void
    {
        self::validateWeights($weights);

        $config ??= config('scout-postgres.text_search_config', 'simple_unaccent');
        $table = $blueprint->getTable();
        $vectorExpr = self::buildVectorExpression($weights, $config);
        $textExpr = self::buildTextExpression(array_keys($weights));

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
            self::quote("{$table}_search_vector_gin"),
            self::quote($table),
        ));

        DB::statement(sprintf(
            'CREATE INDEX IF NOT EXISTS %s ON %s USING gin(search_text gin_trgm_ops)',
            self::quote("{$table}_search_text_trgm"),
            self::quote($table),
        ));
    }

    public static function drop(LaravelBlueprint $blueprint): void
    {
        $table = $blueprint->getTable();

        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::quote("{$table}_search_vector_gin")));
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::quote("{$table}_search_text_trgm")));
        DB::statement(sprintf('ALTER TABLE %s DROP COLUMN IF EXISTS search_vector', self::quote($table)));
        DB::statement(sprintf('ALTER TABLE %s DROP COLUMN IF EXISTS search_text', self::quote($table)));
    }

    /**
     * @param  array<string, string>  $weights
     */
    private static function validateWeights(array $weights): void
    {
        if ($weights === []) {
            throw new InvalidArgumentException('postgresSearchable() requires at least one column => weight entry.');
        }

        $valid = ['A', 'B', 'C', 'D'];
        foreach ($weights as $column => $weight) {
            if (! in_array($weight, $valid, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Weight for column "%s" must be one of A, B, C, D (got "%s").',
                    $column,
                    $weight,
                ));
            }
        }
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
     */
    private static function buildTextExpression(array $columns): string
    {
        // Use `coalesce` + `||` instead of `concat_ws` because STORED generated
        // columns require an IMMUTABLE expression; `concat_ws` is only STABLE.
        $parts = array_map(
            fn (string $col): string => sprintf("coalesce(%s, '')", self::quote($col)),
            $columns,
        );

        return implode(" || ' ' || ", $parts);
    }

    private static function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
