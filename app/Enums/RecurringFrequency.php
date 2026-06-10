<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum RecurringFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

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
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
