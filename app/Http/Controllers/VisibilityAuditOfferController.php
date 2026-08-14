<?php

namespace App\Http\Controllers;

class VisibilityAuditOfferController extends Controller
{
    /**
     * Public landing page for the discounted GBP Visibility Check offer —
     * no login required. Linked from the Meta Lead Ads "thank you" screen;
     * copy deliberately mirrors that ad's own GBP-only framing (message
     * match). The CTA links to a Razorpay Payment Page, hidden entirely
     * when its URL isn't configured yet.
     *
     * Also still passes websitePaymentUrl/bothPaymentUrl — the Website and
     * "Both" Payment Pages + their webhook tier-matching remain fully
     * built and deployed, just not currently linked from this page's
     * single-offer layout. Reuse them directly if a future page/flow needs
     * that broader offer again; nothing about them was removed.
     */
    public function show()
    {
        return view('offers.visibility-audit', [
            'gbpPaymentUrl' => config('services.razorpay.payment_pages.gbp_audit'),
            'websitePaymentUrl' => config('services.razorpay.payment_pages.website_audit'),
            'bothPaymentUrl' => config('services.razorpay.payment_pages.both_audit'),
        ]);
    }
}
