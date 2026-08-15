<?php

namespace App\Http\Controllers;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Two invisible redirect hops in front of the Visibility Audit offer, so the
 * CRM can tell apart the funnel's real drop-off points (lead-form filled but
 * never clicked through / landing page seen but never reached checkout /
 * reached checkout but never paid) instead of only ever seeing "Lead exists"
 * or "Lead paid" — see the payment webhook (RecordVisibilityAuditPurchase)
 * for the "paid" half of this picture.
 *
 * `lead` is an optional query param carrying a Lead's id. Meta's native Lead
 * Ads thank-you-screen "View Website" button is a static URL configured once
 * per form — it cannot inject the submitter's own data — so `enter()` will
 * realistically only ever see it if a future flow starts linking to it some
 * other way (e.g. a personalised WhatsApp send instead of Meta's own button).
 * `checkout()` is the one hop fully inside this app's own control (the
 * landing page renders its own CTA hrefs), so it always carries `lead`
 * forward from `enter()` when one was present. Either way, a missing/invalid
 * `lead` never breaks the redirect — it just logs as an anonymous funnel hit
 * instead of one attributable to a specific Lead.
 */
class VisibilityAuditFunnelTrackingController extends Controller
{
    public function enter(Request $request): RedirectResponse
    {
        $leadId = $this->resolveLeadId($request);

        VisibilityAuditFunnelEvent::create([
            'event_type' => VisibilityAuditFunnelEventType::LandingViewed,
            'lead_id' => $leadId,
        ]);

        return redirect()->route('offers.visibility-audit', array_filter(['lead' => $leadId]));
    }

    public function checkout(Request $request): RedirectResponse
    {
        $leadId = $this->resolveLeadId($request);

        $tier = VisibilityAuditTier::tryFrom((string) $request->query('tier')) ?? VisibilityAuditTier::Gbp;

        VisibilityAuditFunnelEvent::create([
            'event_type' => VisibilityAuditFunnelEventType::PaymentViewed,
            'tier' => $tier,
            'lead_id' => $leadId,
        ]);

        $paymentUrl = config("services.razorpay.payment_pages.{$this->configKeyForTier($tier)}");

        if (! $paymentUrl) {
            // The offer page already hides a tier's CTA entirely when its
            // Payment Page URL isn't configured, so this should be
            // unreachable in practice — fall back to the landing page rather
            // than 404 a real visitor if it ever happens anyway.
            return redirect()->route('offers.visibility-audit', array_filter(['lead' => $leadId]));
        }

        return redirect()->away($paymentUrl);
    }

    private function resolveLeadId(Request $request): ?int
    {
        $leadId = $request->query('lead');

        if (! is_numeric($leadId)) {
            return null;
        }

        return Lead::whereKey((int) $leadId)->exists() ? (int) $leadId : null;
    }

    private function configKeyForTier(VisibilityAuditTier $tier): string
    {
        return match ($tier) {
            VisibilityAuditTier::Gbp => 'gbp_audit',
            VisibilityAuditTier::Website => 'website_audit',
            VisibilityAuditTier::Both => 'both_audit',
        };
    }
}
