<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Tea = 'tea_refreshments';
    case Travel = 'travel';
    case Stationery = 'stationery';
    case Internet = 'internet';
    case Fuel = 'fuel';
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tea => 'Tea / Refreshments',
            self::Travel => 'Travel',
            self::Stationery => 'Stationery',
            self::Internet => 'Internet',
            self::Fuel => 'Fuel',
            self::Rent => 'Rent',
            self::Utilities => 'Utilities',
            self::Other => 'Other',
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
