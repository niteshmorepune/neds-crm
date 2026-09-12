<?php

namespace App\Enums;

/**
 * The 4 entry-level diagnostic offers reachable from the Meta Ads funnel.
 * Single source of truth for price/slug/route across the recommendation
 * matrix, checkout, and admin views — never hardcode a price or route
 * elsewhere. GbpAudit deliberately reuses the pre-existing
 * /offers/visibility-audit page + its own Razorpay Payment Page checkout
 * (VisibilityAuditOfferController) rather than the new in-app checkout the
 * other 3 offers use — that page's payment flow is explicitly out of scope
 * to change (see CLAUDE.md).
 */
enum OfferKey: string
{
    case GbpAudit = 'gbp_audit';
    case LeadGenerationAudit = 'lead_generation_audit';
    case WebsiteGrowthAudit = 'website_growth_audit';
    case GrowthStrategy = 'growth_strategy';

    public function label(): string
    {
        return match ($this) {
            self::GbpAudit => 'Google Business Profile Visibility Audit',
            self::LeadGenerationAudit => 'Lead Generation Funnel Audit',
            self::WebsiteGrowthAudit => 'Website + Conversion Growth Audit',
            self::GrowthStrategy => 'Personalized Digital Growth Strategy',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::GbpAudit => 'GBP Visibility Audit',
            self::LeadGenerationAudit => 'Lead Generation Funnel Audit',
            self::WebsiteGrowthAudit => 'Website Growth Audit',
            self::GrowthStrategy => 'Growth Strategy',
        };
    }

    /** Rupees, for display only — see priceInPaise() for the trusted, transactable amount. */
    public function price(): int
    {
        return match ($this) {
            self::GbpAudit => 120,
            self::LeadGenerationAudit => 299,
            self::WebsiteGrowthAudit => 499,
            self::GrowthStrategy => 999,
        };
    }

    public function priceInPaise(): int
    {
        return $this->price() * 100;
    }

    public function routeName(): string
    {
        return match ($this) {
            self::GbpAudit => 'offers.visibility-audit',
            self::LeadGenerationAudit => 'offers.lead-generation-audit',
            self::WebsiteGrowthAudit => 'offers.website-growth-audit',
            self::GrowthStrategy => 'offers.growth-strategy',
        };
    }

    public function url(): string
    {
        return route($this->routeName());
    }

    /**
     * Whether this offer uses the new in-app Razorpay Orders checkout
     * (OfferCheckoutController) — false only for GbpAudit, which keeps its
     * own pre-existing Payment Page checkout untouched.
     */
    public function usesInAppCheckout(): bool
    {
        return $this !== self::GbpAudit;
    }

    /**
     * Whether checkout collects an extra website_url field before payment —
     * true only for WebsiteGrowthAudit, the one offer this diagnostic
     * actually needs a website to review. Mirrors GBP's own gbp_url
     * capture on the visibility-audit checkout, but scoped to this single
     * offer rather than every one of the 4 — Lead Generation Audit and
     * Growth Strategy have no equivalent single piece of missing
     * information to ask for.
     */
    public function collectsWebsiteUrl(): bool
    {
        return $this === self::WebsiteGrowthAudit;
    }

    public function cta(): string
    {
        return match ($this) {
            self::GbpAudit => 'Get My GBP Audit Offer — ₹120 →',
            self::LeadGenerationAudit => 'Get My Lead Generation Audit — ₹299 →',
            self::WebsiteGrowthAudit => 'Get My Website Growth Audit — ₹499 →',
            self::GrowthStrategy => 'Get My Personalized Growth Strategy — ₹999 →',
        };
    }

    public function deliverable(): string
    {
        return match ($this) {
            self::GbpAudit => 'Google Business Profile Visibility Report',
            self::LeadGenerationAudit => 'Lead Generation Opportunity / Readiness Score',
            self::WebsiteGrowthAudit => 'Website Growth Score + Priority Action Plan',
            self::GrowthStrategy => 'Personalized Digital Growth Roadmap',
        };
    }
}
