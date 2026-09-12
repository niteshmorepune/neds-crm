<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\RazorpayClient;
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
     * choice, not an oversight (see project memory). Every CTA opens an
     * in-app Razorpay Orders + Checkout.js flow (VisibilityAuditCheckoutController)
     * — reversed from this page's original external Payment Page checkout,
     * see the 2026-09-12 "GBP in-app checkout" decisions log entry — gated
     * on `$razorpayConfigured`, same as the other 3 offer pages.
     *
     * `lead` is an optional Lead id carried forward from the `enter`
     * redirect (see that controller's docblock for when it's actually
     * present) — passed through so the checkout flow can prefill the
     * payer's own contact details and attribute the resulting purchase,
     * and just as fine when it's null. `leadGbpUrl` prefills the gbp_url
     * checkout field itself when the lead already gave it earlier (e.g.
     * via the separate Goal-capture flow on their own lead page) — same
     * "don't ask a lead to retype what the CRM already has" reasoning
     * behind capturing it here in the first place.
     *
     * websitePaymentUrl/bothPaymentUrl still passed — the Website and
     * "Both" Payment Pages + their webhook tier-matching remain fully
     * built and deployed, just not currently linked from this page's
     * single-offer layout. Reuse them directly if a future page/flow needs
     * that broader offer again; nothing about them was touched by the GBP
     * tier's move to in-app checkout.
     */
    public function show(Request $request, RazorpayClient $razorpay)
    {
        $leadId = $request->query('lead');
        $leadModel = is_numeric($leadId) ? Lead::find((int) $leadId) : null;

        return view('offers.visibility-audit', [
            'razorpayConfigured' => $razorpay->configured(),
            'websitePaymentUrl' => config('services.razorpay.payment_pages.website_audit'),
            'bothPaymentUrl' => config('services.razorpay.payment_pages.both_audit'),
            'lead' => $leadModel?->id,
            'leadGbpUrl' => $leadModel?->gbp_url,
        ]);
    }
}
