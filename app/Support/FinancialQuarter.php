<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Financial-year quarter math (Apr-Jun = Q1 .. Jan-Mar = Q4), matching the
 * FY convention used everywhere else in this app (invoice numbering, GST
 * reporting — see InvoiceNumberGenerator::financialYear() for the same
 * FY-string formula).
 */
class FinancialQuarter
{
    public static function financialYear(Carbon $date): string
    {
        $year = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $year, ($year + 1) % 100);
    }

    /**
     * 1 (Apr-Jun) .. 4 (Jan-Mar).
     */
    public static function quarterOf(Carbon $date): int
    {
        // Shift so April becomes month 1 of the financial year, then bucket
        // every 3 months into a quarter.
        $fyMonth = ($date->month - 4 + 12) % 12 + 1;

        return (int) ceil($fyMonth / 3);
    }

    /**
     * Start (00:00) / end (23:59:59) instants of a given FY quarter.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(string $financialYear, int $quarter): array
    {
        $startYear = (int) explode('-', $financialYear)[0];
        $startMonth = 4 + ($quarter - 1) * 3;

        // Q4 (Jan-Mar) spills into the calendar year after the FY's start year.
        if ($startMonth > 12) {
            $startMonth -= 12;
            $startYear++;
        }

        $start = Carbon::create($startYear, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(3)->subDay()->endOfDay();

        return [$start, $end];
    }

    /**
     * The FY + quarter that just ended relative to now — the default target
     * for the quarter-close generation command.
     *
     * @return array{financial_year: string, quarter: int}
     */
    public static function previous(): array
    {
        // The last day of the previous quarter is one day before the first
        // day of the quarter we're currently in.
        [$thisQuarterStart] = self::range(self::financialYear(now()), self::quarterOf(now()));
        $lastDayOfPreviousQuarter = $thisQuarterStart->copy()->subDay();

        return [
            'financial_year' => self::financialYear($lastDayOfPreviousQuarter),
            'quarter' => self::quarterOf($lastDayOfPreviousQuarter),
        ];
    }
}
