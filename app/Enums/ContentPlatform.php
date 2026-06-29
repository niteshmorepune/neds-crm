<?php

namespace App\Enums;

enum ContentPlatform: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case LinkedIn = 'linkedin';
    case YouTube = 'youtube';
    case GoogleBusiness = 'google_business';
    case Twitter = 'twitter';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::LinkedIn => 'LinkedIn',
            self::YouTube => 'YouTube',
            self::GoogleBusiness => 'Google Business',
            self::Twitter => 'Twitter / X',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
