<?php

namespace App\Enums;

enum NudgeRecurrence: string
{
    case OneTime = 'one_time';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-time',
            self::Weekly => 'Weekly',
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
