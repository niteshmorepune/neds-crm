<?php

namespace App\Enums;

enum DeliverableStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Received = 'received';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-600',
            self::Submitted => 'bg-amber-100 text-amber-700',
            self::Received => 'bg-green-100 text-green-700',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
