<?php

namespace App\Enums;

enum VisibilityAuditFunnelEventType: string
{
    case LandingViewed = 'landing_viewed';
    case PaymentViewed = 'payment_viewed';

    public function label(): string
    {
        return match ($this) {
            self::LandingViewed => 'Viewed landing page',
            self::PaymentViewed => 'Reached payment page',
        };
    }
}
