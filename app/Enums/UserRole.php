<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Sales = 'sales';
    case Support = 'support';
    case Accounts = 'accounts';

    /**
     * Human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Sales => 'Sales',
            self::Support => 'Support',
            self::Accounts => 'Accounts',
        };
    }

    /**
     * All role values, useful for migrations, validation and seeders.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
