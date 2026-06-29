<?php

namespace App\Enums;

enum ContentWorkflowType: string
{
    case AgencyLed = 'agency_led';
    case NedsLed = 'neds_led';

    public function label(): string
    {
        return match ($this) {
            self::AgencyLed => 'Agency-led (agency creates content)',
            self::NedsLed => 'NEDS-led (we write copy, agency creates visuals)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
