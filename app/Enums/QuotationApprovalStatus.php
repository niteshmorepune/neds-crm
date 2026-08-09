<?php

namespace App\Enums;

enum QuotationApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending approval',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes requested',
            self::Rejected => 'Rejected',
        };
    }
}
