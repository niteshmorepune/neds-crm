<?php

namespace App\Enums;

enum LeadSource: string
{
    case Website = 'website';
    case Whatsapp = 'whatsapp';
    case MetaAds = 'meta_ads';
    case Referral = 'referral';
    case ColdCall = 'cold_call';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Whatsapp => 'WhatsApp',
            self::MetaAds => 'Meta Ads',
            self::Referral => 'Referral',
            self::ColdCall => 'Cold Call',
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
