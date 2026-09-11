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

    /**
     * The query-param spelling used by the dev/QA recommendation test mode
     * (?goal=generate-leads etc.) — deliberately distinct from the enum's
     * own snake_case value, which predates this and is used elsewhere
     * (imports, DB storage).
     */
    public function slug(): string
    {
        return match ($this) {
            self::GenerateLeads => 'generate-leads',
            self::RankHigher => 'rank-google',
            self::GrowBusiness => 'grow-online',
            self::NotSure => 'not-sure',
        };
    }

    public static function fromSlug(?string $slug): ?self
    {
        return match ($slug) {
            'generate-leads' => self::GenerateLeads,
            'rank-google' => self::RankHigher,
            'grow-online' => self::GrowBusiness,
            'not-sure' => self::NotSure,
            default => null,
        };
    }
}
