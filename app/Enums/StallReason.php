<?php

namespace App\Enums;

/**
 * Why a Lead/Deal has real conversation history but no forward motion —
 * tagged by whoever's working it, right from the Log a Call form or the
 * record's own page. Deliberately a small fixed set (same discipline as
 * CrmQueryType/NudgeAutoDetectType), not free text: the point is to be
 * aggregatable across the whole pipeline for coaching/reporting, which a
 * free-text field never is. Grounded in six real patterns found while
 * manually reading 27 calls for the 2026-09-08 VA Funnel diagnosis —
 * before this enum existed, answering "what's actually blocking these
 * deals" required reading every call note by hand.
 */
enum StallReason: string
{
    case Budget = 'budget';
    case Competitor = 'competitor';
    case Trust = 'trust';
    case Confused = 'confused';
    case AwaitingDecision = 'awaiting_decision';

    public function label(): string
    {
        return match ($this) {
            self::Budget => 'Budget / financial constraint',
            self::Competitor => 'Went with a competitor',
            self::Trust => 'Trust / credibility concern',
            self::Confused => "Didn't understand the offer",
            self::AwaitingDecision => 'Awaiting their decision',
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
