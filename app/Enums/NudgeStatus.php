<?php

namespace App\Enums;

enum NudgeStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Snoozed = 'snoozed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Done => 'Done',
            self::Snoozed => 'Snoozed',
        };
    }
}
