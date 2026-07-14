<?php

namespace App\Enums;

enum BiometricSyncStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Requested',
            self::Completed => 'Synced',
            self::Failed => 'Failed',
        };
    }
}
