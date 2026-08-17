<?php

namespace App\Services;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Powers the Visibility Audit recovery queue — Leads attributably stuck at
 * one of the funnel's tracked stages (see VisibilityAuditFunnelTrackingController)
 * with no completed purchase yet — and funnelSummary(), the full-funnel
 * count-per-stage view. "Filled the Meta lead form but never clicked
 * through at all" (previously not coverable — see LeadObserver's own note)
 * is now identified via `meta_leadgen_id IS NOT NULL AND service_id = GMB`,
 * the same cohort SendVisibilityAuditFirstInviteJob targets — NOT
 * `source = MetaAds`, which a real lead (id 225) proved unreliable: Meta's
 * own auto-sent WhatsApp message can beat the Lead Ads webhook to the CRM,
 * landing the lead as source=whatsapp even though it genuinely submitted
 * the Meta form (meta_leadgen_id gets backfilled either way).
 */
class VisibilityAuditFunnelMetrics
{
    /**
     * Leads who reached the landing page (an attributed `landing_viewed`
     * event) but never reached checkout and never paid.
     */
    public function stuckAtLanding(): Collection
    {
        return $this->stuckQuery(VisibilityAuditFunnelEventType::LandingViewed)
            ->whereDoesntHave('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::PaymentViewed))
            ->get();
    }

    /**
     * Leads who reached checkout (an attributed `payment_viewed` event) but
     * never completed a payment.
     */
    public function stuckAtCheckout(): Collection
    {
        return $this->stuckQuery(VisibilityAuditFunnelEventType::PaymentViewed)->get();
    }

    /**
     * stuckAtLanding(), narrowed to Leads whose latest landing_viewed event
     * hasn't been nudged yet and is older than $olderThan — used by
     * SendVisibilityAuditRecoveryNudges so a lead is only ever nudged once
     * per visit, not once per scheduler run.
     */
    public function pendingLandingNudges(Carbon $olderThan): Collection
    {
        return $this->stuckAtLanding()->filter(function (Lead $lead) use ($olderThan) {
            $latest = $lead->visibilityAuditFunnelEvents->first();

            return $latest !== null && $latest->nudged_at === null && $latest->created_at->lte($olderThan);
        })->values();
    }

    /**
     * stuckAtCheckout(), same narrowing as pendingLandingNudges() above.
     */
    public function pendingCheckoutNudges(Carbon $olderThan): Collection
    {
        return $this->stuckAtCheckout()->filter(function (Lead $lead) use ($olderThan) {
            $latest = $lead->visibilityAuditFunnelEvents->first();

            return $latest !== null && $latest->nudged_at === null && $latest->created_at->lte($olderThan);
        })->values();
    }

    private function stuckQuery(VisibilityAuditFunnelEventType $type)
    {
        return Lead::query()
            ->whereHas('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', $type))
            ->whereDoesntHave('visibilityAuditPurchases')
            ->with(['owner', 'visibilityAuditFunnelEvents' => fn ($q) => $q->where('event_type', $type)->latest()]);
    }

    /**
     * Stage-by-stage counts for the GMB-tagged Meta Ads lead cohort: how
     * many were eligible, invited, reached the landing page, reached
     * checkout, and paid — in that order, each stage a subset of the one
     * before it in spirit (though not strictly a strict funnel query, since
     * a lead could in principle reach a later stage via an older recovery
     * link without a fresh landing_viewed event — rare enough not to chase).
     * $from/$to bound eligibility by Lead.created_at; omit both for
     * all-time totals.
     *
     * @return array{eligible: int, invited: int, landing_viewed: int, checkout_viewed: int, paid: int}
     */
    public function funnelSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $eligibleIds = $this->eligibleLeadsQuery($from, $to)->pluck('id');

        return [
            'eligible' => $eligibleIds->count(),
            'invited' => $this->eligibleLeadsQuery($from, $to)->whereNotNull('visibility_audit_invited_at')->count(),
            'landing_viewed' => VisibilityAuditFunnelEvent::where('event_type', VisibilityAuditFunnelEventType::LandingViewed)
                ->whereIn('lead_id', $eligibleIds)
                ->distinct('lead_id')
                ->count('lead_id'),
            'checkout_viewed' => VisibilityAuditFunnelEvent::where('event_type', VisibilityAuditFunnelEventType::PaymentViewed)
                ->whereIn('lead_id', $eligibleIds)
                ->distinct('lead_id')
                ->count('lead_id'),
            'paid' => VisibilityAuditPurchase::whereIn('lead_id', $eligibleIds)->count(),
        ];
    }

    private function eligibleLeadsQuery(?Carbon $from, ?Carbon $to)
    {
        $gmbServiceId = Service::where('name', 'GMB')->value('id');

        return Lead::query()
            ->whereNotNull('meta_leadgen_id')
            ->where('service_id', $gmbServiceId)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
    }
}
