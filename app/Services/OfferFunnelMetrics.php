<?php

namespace App\Services;

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferKey;
use App\Enums\OfferPurchaseStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The non-GBP counterpart to VisibilityAuditFunnelMetrics's
 * pendingLandingNudges()/pendingCheckoutNudges() — powers
 * App\Console\Commands\SendOfferFunnelRecoveryNudges for the 3 offers
 * reached via the unified goal+budget recommendation matrix
 * (LeadGenerationAudit/WebsiteGrowthAudit/GrowthStrategy). A lead
 * recommended into GbpAudit is deliberately excluded from every method
 * here — that offer stays entirely on the existing, unchanged
 * VisibilityAuditFunnelMetrics/VA recovery-nudge pipeline, never double
 * covered. See the 2026-09-12 "unified funnel" decisions log entry.
 */
class OfferFunnelMetrics
{
    /**
     * Leads whose recommendation was generated (and the first-touch message
     * sent, or attempted) but who never opened the recommendation page and
     * never reached their offer's own page either — the softer "gone quiet
     * before even looking" stage.
     *
     * @return Collection<int, Lead>
     */
    public function pendingRecommendationNudges(Carbon $olderThan): Collection
    {
        return $this->nonGbpQuery()
            ->whereNotNull('recommendation_generated_at')
            ->whereNull('recommendation_viewed_at')
            ->whereNull('offer_viewed_at')
            ->whereNull('offer_clicked_at')
            ->whereDoesntHave('offerPurchases', fn ($q) => $q->where('status', OfferPurchaseStatus::Paid))
            ->with(['offerFunnelEvents' => fn ($q) => $q->where('event_type', OfferFunnelEventType::RecommendationCreated)->latest()])
            ->get()
            ->filter(fn (Lead $lead) => $this->isPendingNudge($lead, $olderThan))
            ->values();
    }

    /**
     * Leads who reached their recommended offer's own page (viewed it, or
     * clicked its CTA to start checkout) but never completed a payment —
     * the hotter, closer-to-converting stage.
     *
     * @return Collection<int, Lead>
     */
    public function pendingOfferNudges(Carbon $olderThan): Collection
    {
        return $this->nonGbpQuery()
            ->where(fn ($q) => $q->whereNotNull('offer_viewed_at')->orWhereNotNull('offer_clicked_at'))
            ->whereDoesntHave('offerPurchases', fn ($q) => $q->where('status', OfferPurchaseStatus::Paid))
            ->with(['offerFunnelEvents' => fn ($q) => $q
                ->whereIn('event_type', [OfferFunnelEventType::OfferViewed, OfferFunnelEventType::OfferCtaClicked])
                ->latest()])
            ->get()
            ->filter(fn (Lead $lead) => $this->isPendingNudge($lead, $olderThan))
            ->values();
    }

    private function isPendingNudge(Lead $lead, Carbon $olderThan): bool
    {
        $latest = $lead->offerFunnelEvents->first();

        return $latest !== null && $latest->nudged_at === null && $latest->created_at->lte($olderThan)
            && ! $lead->hasStaffWhatsappReplySince($latest->created_at);
    }

    private function nonGbpQuery()
    {
        return Lead::query()->where('recommendation_offer_key', '!=', OfferKey::GbpAudit->value);
    }
}
