<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Exceptions;

use RuntimeException;

final class UnsupportedDriverException extends RuntimeException
{
    public static function for(string $driver): self
    {
        return new self(sprintf(
            'Scout Postgres engine requires a pgsql connection, got "%s". '
            .'Set your connection driver to pgsql or configure a dedicated search connection.',
            $driver,
        ));
    }
}
