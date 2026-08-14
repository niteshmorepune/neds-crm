<?php

namespace App\Http\Controllers;

class VisibilityAuditOfferController extends Controller
{
    /**
     * Public landing page for the discounted GBP/Website visibility audit
     * offer — no login required. Linked from the Meta Lead Ads "thank you"
     * screen. Each CTA button links to a Razorpay Payment Page; a tier's
     * button is hidden entirely when its URL isn't configured yet.
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
