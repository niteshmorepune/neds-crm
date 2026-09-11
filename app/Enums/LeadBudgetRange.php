<?php

namespace App\Enums;

/**
 * The Meta Ads lead form's "What is your approximate monthly marketing
 * budget?" question — a bounded registry of the exact 4 options Meta asks,
 * same discipline as LeadGoal/StallReason. Deliberately distinct from
 * `estimated_value` (a free-form rupee estimate used elsewhere, e.g. pipeline
 * reporting) and from `ai_budget_band` (AI-inferred Low/Medium/High) — this
 * is the literal, structured answer to Meta's Q2, which the 16-cell
 * recommendation matrix (App\Support\OfferRecommendationMatrix) keys on
 * directly.
 */
enum LeadBudgetRange: string
{
    case Under3000 = 'under_3000';
    case ThreeToSix = '3000_6000';
    case SixToTwelve = '6000_12000';
    case TwelvePlus = '12000_plus';

    public function label(): string
    {
        return match ($this) {
            self::Under3000 => 'Under ₹3,000',
            self::ThreeToSix => '₹3,000 – ₹6,000',
            self::SixToTwelve => '₹6,000 – ₹12,000',
            self::TwelvePlus => '₹12,000+',
        };
    }

    /**
     * The query-param spelling used by the dev/QA recommendation test mode
     * (?budget=under-3000 etc.) and matched against real Meta form answers.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Under3000 => 'under-3000',
            self::ThreeToSix => '3000-6000',
            self::SixToTwelve => '6000-12000',
            self::TwelvePlus => '12000-plus',
        };
    }

    public static function fromSlug(?string $slug): ?self
    {
        return match ($slug) {
            'under-3000' => self::Under3000,
            '3000-6000' => self::ThreeToSix,
            '6000-12000' => self::SixToTwelve,
            '12000-plus' => self::TwelvePlus,
            default => null,
        };
    }
}
