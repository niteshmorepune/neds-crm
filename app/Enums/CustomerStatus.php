<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Prospect = 'prospect';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Prospect => 'Prospect',
            self::Inactive => 'Inactive',
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
