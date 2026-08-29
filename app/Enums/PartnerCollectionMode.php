<?php

namespace App\Enums;

/**
 * Who actually collects payment from this referred client — only meaningful
 * when Customer.referring_partner_id is set. Drives both whether
 * GenerateRecurringInvoices is allowed to create a real Invoice for the
 * client (never, for PartnerCollects) and which direction
 * ReferralSettlement's share is owed.
 */
enum PartnerCollectionMode: string
{
    case NedsCollects = 'neds_collects';
    case PartnerCollects = 'partner_collects';
    case BilledViaThirdParty = 'billed_via_third_party';

    public function label(): string
    {
        return match ($this) {
            self::NedsCollects => 'NEDS collects',
            self::PartnerCollects => 'Partner collects',
            self::BilledViaThirdParty => 'NEDS collects — via third party',
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
