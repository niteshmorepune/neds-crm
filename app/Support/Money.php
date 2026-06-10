<?php

namespace App\Support;

/**
 * Money is stored as integer paise everywhere (CLAUDE.md). These helpers
 * convert to/from the rupee values shown in forms and the UI.
 */
class Money
{
    public static function toPaise(int|float|string|null $rupees): ?int
    {
        if ($rupees === null || $rupees === '') {
            return null;
        }

        return (int) round(((float) $rupees) * 100);
    }

    public static function toRupees(?int $paise): ?float
    {
        return $paise === null ? null : $paise / 100;
    }

    /**
     * Format paise as Indian rupees, e.g. ₹1,25,000.00.
     */
    public static function format(?int $paise): string
    {
        if ($paise === null) {
            return '—';
        }

        $rupees = $paise / 100;
        $rounded = number_format($rupees, 2, '.', '');
        [$whole, $fraction] = explode('.', $rounded);

        $sign = str_starts_with($whole, '-') ? '-' : '';
        $whole = ltrim($whole, '-');

        // Indian grouping: last 3 digits, then groups of 2.
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $last3 = $rest.','.$last3;
        }

        return "₹{$sign}{$last3}.{$fraction}";
    }
}
