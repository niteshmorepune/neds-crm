<?php

namespace App\Support;

/**
 * Amount-in-words using the Indian numbering system (lakh / crore), for
 * printed quotations and invoices.
 */
class IndianNumber
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /**
     * Convert integer paise to a rupees-in-words string, e.g.
     * "Rupees One Lakh Twenty Five Thousand and Fifty Paise Only".
     */
    public static function toWords(int $paise): string
    {
        $rupees = intdiv($paise, 100);
        $paiseRemainder = $paise % 100;

        $words = 'Rupees '.(self::numberToWords($rupees) ?: 'Zero');

        if ($paiseRemainder > 0) {
            $words .= ' and '.self::numberToWords($paiseRemainder).' Paise';
        }

        return $words.' Only';
    }

    private static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        $parts = [];

        $crore = intdiv($number, 10000000);
        $number %= 10000000;
        if ($crore > 0) {
            $parts[] = self::twoOrThreeDigits($crore).' Crore';
        }

        $lakh = intdiv($number, 100000);
        $number %= 100000;
        if ($lakh > 0) {
            $parts[] = self::twoDigits($lakh).' Lakh';
        }

        $thousand = intdiv($number, 1000);
        $number %= 1000;
        if ($thousand > 0) {
            $parts[] = self::twoDigits($thousand).' Thousand';
        }

        $hundred = intdiv($number, 100);
        $number %= 100;
        if ($hundred > 0) {
            $parts[] = self::ONES[$hundred].' Hundred';
        }

        if ($number > 0) {
            $parts[] = self::twoDigits($number);
        }

        return trim(implode(' ', $parts));
    }

    private static function twoDigits(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        $tens = self::TENS[intdiv($n, 10)];
        $ones = self::ONES[$n % 10];

        return trim($tens.' '.$ones);
    }

    /**
     * Crore segment can exceed 99 (e.g. 100+ crore); recurse for safety.
     */
    private static function twoOrThreeDigits(int $n): string
    {
        return $n < 100 ? self::twoDigits($n) : self::numberToWords($n);
    }
}
