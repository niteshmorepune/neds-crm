<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case HalfDay = 'half_day';
    case Leave = 'leave';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::HalfDay => 'Half Day',
            default => ucfirst($this->value),
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
