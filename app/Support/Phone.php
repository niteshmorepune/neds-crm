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

    /**
     * Digits worth phone-searching a list/search-box query on, or null when
     * the term doesn't contain enough digits to plausibly be a phone-number
     * search — avoids a stray digit inside an otherwise-text query (e.g. a
     * street address in a company name) matching unrelated numbers.
     */
    public static function searchDigits(string $search): ?string
    {
        $digits = self::digits($search);

        return strlen($digits) >= 4 ? $digits : null;
    }

    /**
     * SQL expression stripping common phone-formatting characters (space,
     * +, -, parentheses) from $column, for a normalized LIKE match against
     * searchDigits(). Stored phone numbers in this app are inconsistently
     * formatted (e.g. "+91 98765 43210" vs a plain 10-digit string) — a
     * plain `LIKE` against the raw column misses most real searches typed
     * as bare digits. $column is always a hardcoded column name from this
     * codebase, never user input.
     */
    public static function normalizedSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')";
    }
}
