<?php

namespace App\Enums;

enum VisibilityAuditTier: string
{
    case Gbp = 'gbp';
    case Website = 'website';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Gbp => 'GBP Audit',
            self::Website => 'Website Audit',
            self::Both => 'GBP + Website Audit',
        };
    }

    /**
     * Matches a captured payment's amount to an offer tier. Kept as an
     * amount lookup (not read from the webhook payload) since the exact
     * shape of a Razorpay Payment Page's notes/custom fields is unverified
     * against a real payment — the amount is the one thing guaranteed to be
     * both present and correct on every payment.captured event.
     */
    public static function fromAmountPaise(int $amountPaise): ?self
    {
        return match ($amountPaise) {
            12000 => self::Gbp,
            24000 => self::Website,
            36000 => self::Both,
            default => null,
        };
    }
}
