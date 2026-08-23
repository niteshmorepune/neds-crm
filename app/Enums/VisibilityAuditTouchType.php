<?php

namespace App\Enums;

enum VisibilityAuditTouchType: string
{
    case FirstInvite = 'first_invite';
    case RecoveryNudgeLanding = 'recovery_nudge_landing';
    case RecoveryNudgeCheckout = 'recovery_nudge_checkout';
    case PaymentConfirmation = 'payment_confirmation';
    case ManualOutreach = 'manual_outreach';
    case CustomerReply = 'customer_reply';

    public function label(): string
    {
        return match ($this) {
            self::FirstInvite => 'First invite',
            self::RecoveryNudgeLanding => 'Recovery nudge (landing)',
            self::RecoveryNudgeCheckout => 'Recovery nudge (checkout)',
            self::PaymentConfirmation => 'Payment confirmation',
            self::ManualOutreach => 'Manual outreach',
            self::CustomerReply => 'Customer reply',
        };
    }
}
