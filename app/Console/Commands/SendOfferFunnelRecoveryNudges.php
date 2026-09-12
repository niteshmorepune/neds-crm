<?php

namespace App\Console\Commands;

use App\Enums\OfferFunnelEventType;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Jobs\SendOfferRecoveryNudgeJob;
use App\Jobs\SendVisibilityAuditRecoveryNudgeEmailJob;
use App\Jobs\SendVisibilityAuditRecoveryNudgeJob;
use App\Services\OfferFunnelMetrics;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Console\Command;

/**
 * One recovery-nudge cron for the WHOLE unified offer/recommendation funnel,
 * regardless of which of the 4 offers a lead was recommended into — replaces
 * the old, standalone SendVisibilityAuditRecoveryNudges (now deleted; its
 * own handle() body is inlined below unchanged). See the 2026-09-12 "unified
 * funnel" decisions log entry: GbpAudit-recommended leads still go through
 * the existing, untouched VisibilityAuditFunnelMetrics/VA nudge jobs — the
 * other 3 offers go through the new OfferFunnelMetrics/SendOfferRecoveryNudgeJob
 * pair. One cron entry, one combined count, one mental model — "who's
 * stalled in the funnel" — instead of two independent commands each unaware
 * of the other.
 */
class SendOfferFunnelRecoveryNudges extends Command
{
    protected $signature = 'app:send-offer-funnel-recovery-nudges';

    protected $description = 'Dispatch WhatsApp (+ email, for GBP) recovery nudges across the whole offer/recommendation funnel, for every Lead stuck at a tracked stage (run every 30 minutes via scheduler).';

    /**
     * Same 2h/4h split as the old VA-only command, applied identically to
     * the new offer stages — "offer" (closer to converting) gets the
     * shorter wait, "recommendation" (softer signal) gets the longer one.
     */
    private const OFFER_WAIT_HOURS = 2;

    private const RECOMMENDATION_WAIT_HOURS = 4;

    public function handle(VisibilityAuditFunnelMetrics $vaMetrics, OfferFunnelMetrics $offerMetrics): int
    {
        $sent = 0;

        // GbpAudit-recommended leads — unchanged VA pipeline.
        foreach ($vaMetrics->pendingCheckoutNudges(now()->subHours(self::OFFER_WAIT_HOURS)) as $lead) {
            $eventId = $lead->visibilityAuditFunnelEvents->first()->id;
            SendVisibilityAuditRecoveryNudgeJob::dispatch($lead->id, $eventId, VisibilityAuditFunnelEventType::PaymentViewed);
            SendVisibilityAuditRecoveryNudgeEmailJob::dispatch($lead->id, $eventId, VisibilityAuditFunnelEventType::PaymentViewed);
            $sent++;
        }

        foreach ($vaMetrics->pendingLandingNudges(now()->subHours(self::RECOMMENDATION_WAIT_HOURS)) as $lead) {
            $eventId = $lead->visibilityAuditFunnelEvents->first()->id;
            SendVisibilityAuditRecoveryNudgeJob::dispatch($lead->id, $eventId, VisibilityAuditFunnelEventType::LandingViewed);
            SendVisibilityAuditRecoveryNudgeEmailJob::dispatch($lead->id, $eventId, VisibilityAuditFunnelEventType::LandingViewed);
            $sent++;
        }

        // The other 3 offers — new unified funnel pipeline.
        foreach ($offerMetrics->pendingOfferNudges(now()->subHours(self::OFFER_WAIT_HOURS)) as $lead) {
            $eventId = $lead->offerFunnelEvents->first()->id;
            SendOfferRecoveryNudgeJob::dispatch($lead->id, $eventId, OfferFunnelEventType::OfferViewed);
            $sent++;
        }

        foreach ($offerMetrics->pendingRecommendationNudges(now()->subHours(self::RECOMMENDATION_WAIT_HOURS)) as $lead) {
            $eventId = $lead->offerFunnelEvents->first()->id;
            SendOfferRecoveryNudgeJob::dispatch($lead->id, $eventId, OfferFunnelEventType::RecommendationCreated);
            $sent++;
        }

        $this->info("Dispatched {$sent} offer funnel recovery nudge(s).");

        return self::SUCCESS;
    }
}
