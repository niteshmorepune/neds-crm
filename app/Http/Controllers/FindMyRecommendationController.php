<?php

namespace App\Http\Controllers;

use App\Actions\GenerateLeadRecommendation;
use App\Enums\OfferKey;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The one URL given to Meta as the Lead Ads Instant Form's own Thank-You-
 * screen "Website URL" — see the 2026-09-12 "dynamic landing page" decision.
 * Meta's own thank-you-screen button is a single static URL with no per-
 * submission personalization of any kind (confirmed: it cannot carry a
 * lead id, phone, or any of the submitter's own answers — that data isn't
 * available client-side at all, only delivered to us later via webhook),
 * so this page makes it "dynamic" the only way actually possible: it asks
 * the visitor to confirm the phone number they just gave Meta, looks up the
 * matching Lead the webhook already created, and forwards them straight to
 * their own resolved recommendation — the same GenerateLeadRecommendation
 * decision every other channel (WhatsApp, email) already uses.
 *
 * Deliberately reuses Lead::findOpenByPhone() (the same matching every other
 * wadesk.in/Meta-facing lookup in this app already relies on) rather than
 * inventing a new lookup — a phone number is not unguessable the way a
 * recommendation_token is, so the lookup route carries a stricter throttle
 * than a normal page view to make casual enumeration impractical.
 */
class FindMyRecommendationController extends Controller
{
    public function show(): View
    {
        return view('offers.find-my-recommendation');
    }

    public function lookup(Request $request, GenerateLeadRecommendation $generate): View|RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);

        $lead = Lead::findOpenByPhone($data['phone']);

        if ($lead === null) {
            return view('offers.find-my-recommendation', [
                'error' => "We couldn't find a submission with that number yet. If you just submitted the form, please wait a minute and try again.",
            ]);
        }

        $recommendation = $generate->handle($lead);

        if ($recommendation === null) {
            return view('offers.find-my-recommendation', [
                'error' => "We've got your details, but we're still working out your personalized recommendation. We'll message you on WhatsApp shortly — no need to wait here.",
            ]);
        }

        // Route GBP through its own existing tracking hop (same one every
        // other channel already uses) so this counts as a real
        // LandingViewed hit; the other 3 offers' RecommendationViewed event
        // is already logged by OfferRecommendationController::show() itself,
        // no separate hop needed — see that controller's own docblock.
        if ($recommendation->offerKey === OfferKey::GbpAudit) {
            return redirect()->route('offers.visibility-audit.enter', ['lead' => $lead->id]);
        }

        return redirect()->to($lead->recommendationUrl());
    }
}
