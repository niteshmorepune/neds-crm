<?php

namespace App\Enums;

enum ClientAdvanceStatus: string
{
    case Outstanding = 'outstanding';
    case PartiallyApplied = 'partially_applied';
    case FullyApplied = 'fully_applied';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PartiallyApplied => 'Partially Applied',
            self::FullyApplied => 'Fully Applied',
            default => ucfirst($this->value),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Outstanding => 'bg-amber-100 text-amber-700',
            self::PartiallyApplied => 'bg-blue-100 text-blue-700',
            self::FullyApplied => 'bg-green-100 text-green-700',
            self::Cancelled => 'bg-gray-100 text-gray-500',
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
