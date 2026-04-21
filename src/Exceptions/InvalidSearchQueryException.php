<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Exceptions;

use RuntimeException;

final class InvalidSearchQueryException extends RuntimeException
{
    public static function forQuery(string $query, string $reason): self
    {
        return new self(sprintf(
            'Failed to parse search query "%s": %s.',
            $query,
            $reason,
        ));
    }
}
