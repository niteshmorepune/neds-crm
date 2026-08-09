<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum RecurringFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Advance a date by one interval of this frequency.
     */
    public function advance(Carbon $date): Carbon
    {
        return match ($this) {
            self::Monthly => $date->copy()->addMonthNoOverflow(),
            self::Quarterly => $date->copy()->addMonthsNoOverflow(3),
            self::Yearly => $date->copy()->addYearNoOverflow(),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * How many months one billing cycle of this frequency spans, for
     * normalizing a cycle amount to a monthly-equivalent figure. Mirrors
     * BusinessOverviewMetrics::CYCLE_MONTHS exactly — kept here too so a
     * single client's own MRR (Customer::monthlyRecurringValue()) can be
     * computed without depending on that report service.
     */
    public function cycleMonths(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }
}
