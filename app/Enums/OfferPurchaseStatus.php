<?php

namespace App\Enums;

enum OfferPurchaseStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Successful',
            self::Failed => 'Failed',
        };
    }
}
