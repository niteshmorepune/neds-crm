<?php

namespace App\Services;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;

/**
 * Powers the Visibility Audit recovery queue — Leads attributably stuck at
 * one of the funnel's tracked stages (see VisibilityAuditFunnelTrackingController)
 * with no completed purchase yet. Deliberately does NOT cover "filled the
 * Meta lead form but never clicked through at all" — that would need a
 * reliable way to tell which Leads belong to this specific campaign, which
 * doesn't exist yet (Meta's own utm_campaign string isn't a safe filter
 * without confirming its exact value), so it's left out rather than guessed.
 */
class VisibilityAuditFunnelMetrics
{
    /**
     * Leads who reached the landing page (an attributed `landing_viewed`
     * event) but never reached checkout and never paid.
     */
    public function stuckAtLanding(): Collection
    {
        return Lead::query()
            ->whereHas('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::LandingViewed))
            ->whereDoesntHave('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::PaymentViewed))
            ->whereDoesntHave('visibilityAuditPurchases')
            ->with(['owner', 'visibilityAuditFunnelEvents' => fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::LandingViewed)->latest()])
            ->get();
    }

    /**
     * Leads who reached checkout (an attributed `payment_viewed` event) but
     * never completed a payment.
     */
    public function stuckAtCheckout(): Collection
    {
        return Lead::query()
            ->whereHas('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::PaymentViewed))
            ->whereDoesntHave('visibilityAuditPurchases')
            ->with(['owner', 'visibilityAuditFunnelEvents' => fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::PaymentViewed)->latest()])
            ->get();
    }
}
