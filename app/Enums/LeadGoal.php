<?php

namespace App\Enums;

/**
 * The Meta Ads lead form's "What is your biggest goal?" question, now a
 * structured field any telecaller/sales rep can also set directly (not
 * Meta-only) — see the 2026-09-08 CLAUDE.md decisions log entry. Bounded
 * registry, not free text, same discipline as StallReason/TargetMetric.
 */
enum LeadGoal: string
{
    case GenerateLeads = 'generate_leads';
    case RankHigher = 'rank_higher';
    case GrowBusiness = 'grow_business';
    case NotSure = 'not_sure';

    public function label(): string
    {
        return match ($this) {
            self::GenerateLeads => 'Generate More Leads',
            self::RankHigher => 'Rank Higher on Google',
            self::GrowBusiness => 'Grow My Business Online',
            self::NotSure => 'Not Sure – Need Expert Advice',
        };
    }

    /**
     * Goals 1-3 point to a concrete next step (get their Website/GBP link
     * so the team can review it); NotSure means they need a real
     * conversation instead — see the "next step" banner on the lead page.
     */
    public function needsWebsiteOrGbp(): bool
    {
        return $this !== self::NotSure;
    }
}
