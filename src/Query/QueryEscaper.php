<?php

declare(strict_types=1);

namespace ApexScout\ScoutPostgres\Query;

use Normalizer;

final class QueryEscaper
{
    /** @var list<string> */
    private const array TSQUERY_SPECIALS = ['&', '|', '!', '(', ')', '<', '>', ':', '*', "'", '"'];

    public static function escapeForTsquery(string $input): string
    {
        $cleaned = str_replace(self::TSQUERY_SPECIALS, '', $input);

        return mb_trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
    }

    public static function buildPrefixQuery(string $input): string
    {
        $cleaned = self::escapeForTsquery($input);
        if ($cleaned === '') {
            return '';
        }

        $tokens = explode(' ', $cleaned);
        $last = array_pop($tokens).':*';

        return $tokens === [] ? $last : implode(' & ', $tokens).' & '.$last;
    }

    public static function normaliseForTrigram(string $input): string
    {
        $normalised = $input;
        if (class_exists(Normalizer::class)) {
            $result = Normalizer::normalize($input, Normalizer::FORM_D);
            if (is_string($result) && $result !== '') {
                $normalised = $result;
            }
        }

        $ascii = preg_replace('/\p{Mn}+/u', '', $normalised) ?? $normalised;
        $collapsed = preg_replace('/\s+/', ' ', $ascii) ?? $ascii;

        return mb_strtolower(mb_trim($collapsed));
    }
}
