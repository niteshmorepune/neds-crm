<?php

namespace App\Actions;

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferKey;
use App\Jobs\SendOfferRecommendationReadyJob;
use App\Jobs\SendVisibilityAuditFirstInviteEmailJob;
use App\Jobs\SendVisibilityAuditFirstInviteJob;
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
 *
 * Also the single dispatch point for the unified funnel's first-touch
 * WhatsApp message (see the 2026-09-12 "unified funnel" decisions log
 * entry) — the goal+budget matrix decides which of the 4 offers fits a
 * lead, regardless of which service it was tagged, and this is where that
 * decision turns into an actual message:
 *   - offerKey === GbpAudit  -> the existing, unchanged
 *     SendVisibilityAuditFirstInviteJob/-EmailJob (same templates, same
 *     landing page, same Payment Page checkout — nothing about that
 *     pipeline changes).
 *   - any other offerKey     -> the new SendOfferRecommendationReadyJob,
 *     pointing at this lead's own /offers/recommendation/{token} page.
 * Only ever fires for a genuine Meta Ads lead (meta_leadgen_id set — never
 * auto-messages a lead whose goal/budget a rep filled in by hand on a
 * Website/Referral/etc. lead), and only once per lead ever
 * (SendOfferRecommendationReadyJob's own idempotency guard on
 * Lead.recommendation_notified_at covers the non-GBP branch; the VA job's
 * own visibility_audit_invited_at guard covers the GBP branch).
 *
 * LeadObserver::routeMetaLeadFirstTouch() calls this action first for every
 * eligible Meta lead; only when it returns null (goal/budget haven't been
 * captured/parsed yet) does the observer fall back to its own service-tag
 * based routing as a safety net.
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

            if ($lead->meta_leadgen_id !== null) {
                if ($recommendation->offerKey === OfferKey::GbpAudit) {
                    SendVisibilityAuditFirstInviteJob::dispatch($lead->id);
                    SendVisibilityAuditFirstInviteEmailJob::dispatch($lead->id);
                } else {
                    SendOfferRecommendationReadyJob::dispatch($lead->id);
                }
            }
        }

        return $recommendation;
    }
}
