<?php

namespace App\Enums;

enum TargetPeriodType: string
{
    case Month = 'month';
    case FinancialYear = 'financial_year';

    public function label(): string
    {
        return match ($this) {
            self::Month => 'Monthly',
            self::FinancialYear => 'This financial year',
        };
    }

    /**
     * The canonical `period_start` for "now" under this period type —
     * first of the current month, or 1 April of the current financial year.
     */
    public function currentPeriodStart(): \Illuminate\Support\Carbon
    {
        $now = now();

        return match ($this) {
            self::Month => $now->copy()->startOfMonth()->startOfDay(),
            self::FinancialYear => \Illuminate\Support\Carbon::create(
                $now->month >= 4 ? $now->year : $now->year - 1,
                4,
                1
            )->startOfDay(),
        };
    }
}
