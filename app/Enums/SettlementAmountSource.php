<?php

namespace App\Enums;

/**
 * How a ReferralSettlement row's billed_amount was determined.
 */
enum SettlementAmountSource: string
{
    case Invoice = 'invoice';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'From invoice',
            self::Manual => 'Manually entered',
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
