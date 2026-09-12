<?php

namespace App\Http\Controllers;

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferKey;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Services\RazorpayClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The 3 new entry-offer landing pages (Lead Generation Audit / Website
 * Growth Audit / Growth Strategy) — visually mirrors the pre-existing
 * /offers/visibility-audit page (VisibilityAuditOfferController), which
 * this controller deliberately does not touch or reuse routing for. `lead`
 * is an optional numeric id carried forward from the recommendation page's
 * CTA (same harmless ?lead= convention that page already uses) — present
 * only to attribute a later purchase/CTA click back to a specific Lead and
 * to prefill the checkout's contact details; never required, and this page
 * never renders anything about the lead itself. `leadWebsiteUrl` is passed
 * for every offer for simplicity, but only the Website Growth Audit view
 * actually renders it — see OfferKey::collectsWebsiteUrl().
 */
class OfferPageController extends Controller
{
    public function leadGenerationAudit(Request $request, RazorpayClient $razorpay): View
    {
        return $this->render($request, $razorpay, OfferKey::LeadGenerationAudit, 'offers.lead-generation-audit');
    }

    public function websiteGrowthAudit(Request $request, RazorpayClient $razorpay): View
    {
        return $this->render($request, $razorpay, OfferKey::WebsiteGrowthAudit, 'offers.website-growth-audit');
    }

    public function growthStrategy(Request $request, RazorpayClient $razorpay): View
    {
        return $this->render($request, $razorpay, OfferKey::GrowthStrategy, 'offers.growth-strategy');
    }

    private function render(Request $request, RazorpayClient $razorpay, OfferKey $offer, string $view): View
    {
        $leadId = $request->query('lead');
        $lead = is_numeric($leadId) ? Lead::find((int) $leadId) : null;

        if ($lead !== null) {
            if ($lead->offer_viewed_at === null) {
                $lead->forceFill(['offer_viewed_at' => now()])->saveQuietly();
            }

            OfferFunnelEvent::create([
                'event_type' => OfferFunnelEventType::OfferViewed,
                'offer_key' => $offer->value,
                'lead_id' => $lead->id,
            ]);
        }

        return view($view, [
            'offerKey' => $offer,
            'leadId' => $lead?->id,
            'leadWebsiteUrl' => $lead?->website_url,
            'razorpayConfigured' => $razorpay->configured(),
        ]);
    }
}
