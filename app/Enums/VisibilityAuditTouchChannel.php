<?php

namespace App\Enums;

enum VisibilityAuditTouchChannel: string
{
    case AiWhatsapp = 'ai_whatsapp';
    case AiEmail = 'ai_email';
    case StaffCall = 'staff_call';
    case CustomerWhatsapp = 'customer_whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::AiWhatsapp => 'AI (WhatsApp)',
            self::AiEmail => 'AI (Email)',
            self::StaffCall => 'Staff (call)',
            self::CustomerWhatsapp => 'Customer (WhatsApp)',
        };
    }
}
