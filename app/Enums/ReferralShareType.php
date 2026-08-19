<?php

namespace App\Enums;

/**
 * How a referred client's ReferralSettlement share_amount is computed —
 * only meaningful when Customer.referring_partner_id is set. null on the
 * customer is treated as Percentage (this project's original design, still
 * valid for a client whose split genuinely scales with billing).
 */
enum ReferralShareType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage of billing',
            self::FixedAmount => 'Fixed amount per month',
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
