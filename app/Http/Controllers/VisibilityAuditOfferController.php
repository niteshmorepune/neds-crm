<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VisibilityAuditOfferController extends Controller
{
    /**
     * Public landing page for the discounted GBP Audit offer — no login
     * required. Linked from the Meta Lead Ads "thank you" screen (via the
     * funnel-tracking `enter` redirect, not directly); copy is a fully
     * custom design (owner-supplied HTML/CSS, converted to Blade) mirroring
     * that ad's own GBP-only framing and Hindi/English mix (message match).
     * Deliberately uses "Audit" throughout, not "Review" — a considered
     * choice, not an oversight (see project memory). Every CTA links to the
     * funnel-tracking `checkout` redirect (see
     * VisibilityAuditFunnelTrackingController), which forwards to the real
     * Razorpay Payment Page — hidden entirely when the page's URL isn't
     * configured yet.
     *
     * `lead` is an optional Lead id carried forward from the `enter`
     * redirect (see that controller's docblock for when it's actually
     * present) — passed through unchanged to every `checkout` CTA on this
     * page so a funnel event can still be attributed to a specific Lead
     * further down the funnel, and just as fine when it's null.
     *
     * Also still passes websitePaymentUrl/bothPaymentUrl — the Website and
     * "Both" Payment Pages + their webhook tier-matching remain fully
     * built and deployed, just not currently linked from this page's
     * single-offer layout. Reuse them directly if a future page/flow needs
     * that broader offer again; nothing about them was removed.
     */
    public function show(Request $request)
    {
        $leadId = $request->query('lead');
        $lead = is_numeric($leadId) ? (int) $leadId : null;

        return view('offers.visibility-audit', [
            'gbpPaymentUrl' => config('services.razorpay.payment_pages.gbp_audit'),
            'websitePaymentUrl' => config('services.razorpay.payment_pages.website_audit'),
            'bothPaymentUrl' => config('services.razorpay.payment_pages.both_audit'),
            'lead' => $lead,
        ]);
    }
}
