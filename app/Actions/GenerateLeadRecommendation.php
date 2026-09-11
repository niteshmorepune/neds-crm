<?php

namespace App\Actions;

use App\Enums\OfferFunnelEventType;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Support\OfferRecommendation;
use App\Support\OfferRecommendationMatrix;
use Illuminate\Support\Str;

/**
 * Idempotently resolves a Lead's recommendation from OfferRecommendationMatrix
 * and persists it. Safe to call on every recommendation-page request (spec
 * requirement: recommendation generation must be idempotent) — only writes
 * to the Lead when something has genuinely changed (the cell itself, or a
 * still-missing token/generated-at), and only ever logs a
 * RecommendationCreated funnel event on a real (re)generation, never a
 * no-op call.
 */
class GenerateLeadRecommendation
{
    public function handle(Lead $lead): ?OfferRecommendation
    {
        if ($lead->goal === null || $lead->budget_range === null) {
            return null;
        }

        $recommendation = OfferRecommendationMatrix::for($lead->goal, $lead->budget_range);

        $dirty = [];

        if ($lead->recommendation_key !== $recommendation->recommendationKey) {
            $dirty['recommendation_key'] = $recommendation->recommendationKey;
            $dirty['recommendation_offer_key'] = $recommendation->offerKey->value;
        }

        if ($lead->recommendation_generated_at === null) {
            $dirty['recommendation_generated_at'] = now();
        }

        if ($lead->recommendation_token === null) {
            $dirty['recommendation_token'] = (string) Str::uuid();
        }

        if ($dirty !== []) {
            $lead->forceFill($dirty)->saveQuietly();

            OfferFunnelEvent::create([
                'event_type' => OfferFunnelEventType::RecommendationCreated,
                'offer_key' => $recommendation->offerKey->value,
                'lead_id' => $lead->id,
            ]);
        }

        return $recommendation;
    }
}
