<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case Staff = 'staff';
    case Clients = 'clients';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff only',
            self::Clients => 'Clients only',
            self::Both => 'Staff & Clients',
        };
    }

    public function includesStaff(): bool
    {
        return $this === self::Staff || $this === self::Both;
    }

    public function includesClients(): bool
    {
        return $this === self::Clients || $this === self::Both;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
