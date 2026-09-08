<?php

namespace App\Enums;

enum LeadSource: string
{
    case Website = 'website';
    case Whatsapp = 'whatsapp';
    case MetaAds = 'meta_ads';
    case Referral = 'referral';
    case ColdCall = 'cold_call';
    case PhoneEnquiry = 'phone_enquiry';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Whatsapp => 'WhatsApp',
            self::MetaAds => 'Meta Ads',
            self::Referral => 'Referral',
            self::ColdCall => 'Cold Call',
            self::PhoneEnquiry => 'Phone Enquiry',
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

    /**
     * True when the lead itself chose the moment of first contact — filled
     * a form, messaged, or called in — so its creation time is a real
     * personal availability signal. False for Cold Call (staff dialed, at
     * whatever time suited the rep) and Referral/Other (the timestamp
     * reflects when someone entered the record, not when the prospect
     * acted). Used by LeadCallTimingAdvisor to gate the "captured around
     * this hour" call-timing signal to sources where it's actually
     * meaningful.
     */
    public function isProspectInitiated(): bool
    {
        return match ($this) {
            self::Website, self::Whatsapp, self::MetaAds, self::PhoneEnquiry => true,
            self::Referral, self::ColdCall, self::Other => false,
        };
    }
}
