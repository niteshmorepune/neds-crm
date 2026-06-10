<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * SLA resolution window in business hours (CLAUDE.md / BUILD_PLAN).
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::Urgent => 4,
            self::High => 8,
            self::Normal => 24,
            self::Low => 72,
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
