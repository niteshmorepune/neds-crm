<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            default => ucfirst($this->value),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
