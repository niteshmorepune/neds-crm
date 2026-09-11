<?php

namespace App\Http\Controllers;

use App\Actions\GenerateLeadRecommendation;
use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferFunnelEventType;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Support\OfferRecommendationMatrix;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The personalized recommendation page (/offers/recommendation/{token}) —
 * the centerpiece of the Meta Ads funnel: goal + budget -> the 16-cell
 * OfferRecommendationMatrix -> one of the 4 entry offers. Reached only by
 * its unguessable recommendation_token (see Lead::recommendationUrl()),
 * never a bare lead id — this page renders real name/goal/budget back on
 * screen, unlike the existing VA offer page's harmless numeric ?lead=.
 */
class OfferRecommendationController extends Controller
{
    public function show(string $token, GenerateLeadRecommendation $generate): View|Response
    {
        $lead = Lead::where('recommendation_token', $token)->first();
        $recommendation = $lead !== null ? $generate->handle($lead) : null;

        if ($lead === null || $recommendation === null) {
            return response()->view('offers.recommendation-unavailable', status: 404);
        }

        if ($lead->recommendation_viewed_at === null) {
            $lead->forceFill(['recommendation_viewed_at' => now()])->saveQuietly();
        }

        OfferFunnelEvent::create([
            'event_type' => OfferFunnelEventType::RecommendationViewed,
            'offer_key' => $recommendation->offerKey->value,
            'lead_id' => $lead->id,
        ]);

        return view('offers.recommendation', [
            'lead' => $lead,
            'recommendation' => $recommendation,
        ]);
    }

    /**
     * Dev/QA test mode — /offers/recommendation/test?goal=...&budget=....
     * Deliberately 404s outside local/testing: production recommendations
     * must always come from a real Lead's stored goal/budget, never a query
     * parameter a visitor could set themselves to manipulate price/offer.
     */
    public function test(Request $request): View
    {
        abort_if(app()->environment('production'), 404);

        $goal = LeadGoal::fromSlug($request->query('goal'));
        $budget = LeadBudgetRange::fromSlug($request->query('budget'));

        abort_if($goal === null || $budget === null, 404, 'Provide ?goal=...&budget=... — see LeadGoal/LeadBudgetRange::fromSlug() for valid values.');

        return view('offers.recommendation', [
            'lead' => null,
            'recommendation' => OfferRecommendationMatrix::for($goal, $budget),
        ]);
    }
}
