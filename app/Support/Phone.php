<?php

namespace App\Support;

/**
 * Shared phone-number normalization — every lookup/matching call site in
 * this app (Customer lookup, cross-channel Lead dedup, outbound wadesk.in
 * calls) needs the exact same normalization or matches silently fail.
 * Previously duplicated inline in three places; consolidated here.
 */
class Phone
{
    public static function digits(string $raw): string
    {
        return preg_replace('/\D/', '', $raw) ?? '';
    }

    /**
     * Last 10 digits — the matching key used for lookups, since stored
     * numbers inconsistently include/omit a country code prefix.
     */
    public static function last10(string $raw): string
    {
        $digits = self::digits($raw);

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }
}
