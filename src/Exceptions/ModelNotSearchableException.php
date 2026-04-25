<?php

declare(strict_types=1);

namespace ScoutPostgres\Exceptions;

use RuntimeException;

final class ModelNotSearchableException extends RuntimeException
{
    public static function for(string $table): self
    {
        return new self(sprintf(
            'Table "%s" has no "search_vector" column. '
            .'Add one via $table->postgresSearchable([...]) in a migration.',
            $table,
        ));
    }
}
