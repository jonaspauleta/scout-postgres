<?php

declare(strict_types=1);

namespace ScoutPostgres\Exceptions;

use RuntimeException;

final class MissingPostgresExtensionException extends RuntimeException
{
    public static function for(string $extension): self
    {
        return new self(sprintf(
            'Required Postgres extension "%s" is not installed. '
            .'Run: CREATE EXTENSION IF NOT EXISTS %s; On Neon/Laravel Cloud the database owner role is authorised to run this.',
            $extension,
            $extension,
        ));
    }
}
