<?php

namespace App\Enums;

enum VisibilityAuditTouchChannel: string
{
    case AiWhatsapp = 'ai_whatsapp';
    case StaffCall = 'staff_call';

    public function label(): string
    {
        return match ($this) {
            self::AiWhatsapp => 'AI (WhatsApp)',
            self::StaffCall => 'Staff (call)',
        };
    }
}
