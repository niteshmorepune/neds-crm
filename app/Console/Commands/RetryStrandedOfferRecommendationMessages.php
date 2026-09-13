<?php

namespace App\Console\Commands;

use App\Enums\OfferKey;
use App\Jobs\SendOfferRecommendationReadyJob;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Real incident, 2026-09-13 (see the "97->8->2->0 drop-off" investigation):
 * SendOfferRecommendationReadyJob deliberately swallows a non-2xx response
 * from wadesk.in as a Log::warning(); return -- no throw, no failed_jobs
 * row, by design ("an integration failure must never break core workflow").
 * That's correct for the job itself, but it also means a lead whose one and
 * only send attempt failed has no automated path to a second one:
 * GenerateLeadRecommendation::handle() only ever dispatches the job again if
 * the recommendation cell itself changes ($dirty !== []), which a lead
 * simply sitting untouched will never trigger on its own.
 *
 * 71 leads were stranded this way by two now-fixed wadesk.in-side issues
 * (template sync, then a bilingual-variable-count bug) — this is a one-shot
 * safety net for exactly that shape of outage, not a general retry loop.
 * Mirrors RetryFailedLeadWelcomeMessages' own role in the funnel (a stranded-
 * lead sweep, not a queue-level retry), but tracks eligibility via a plain
 * timestamp column (recommendation_retry_attempted_at) rather than a note-
 * counting pattern, since SendOfferRecommendationReadyJob writes no failure
 * note to count in the first place (unlike SendLeadWelcomeMessageJob, whose
 * retry precedent this was modelled on) -- and unlike that job's own
 * multi-attempt/give-up-after-N design, a lead here gets AT MOST ONE
 * automated retry ever, full stop, then is left to a human.
 */
class RetryStrandedOfferRecommendationMessages extends Command
{
    protected $signature = 'app:retry-stranded-offer-recommendation-messages';

    protected $description = 'One-time automated retry of the recommendation-ready WhatsApp message for a non-GBP offer lead whose original send never succeeded (run daily; safe to run manually/repeatedly).';

    /**
     * How long to wait after recommendation_generated_at before treating a
     * still-un-notified lead as a genuine failure rather than "the queue
     * just hasn't gotten to it yet" — the job normally runs within seconds
     * of dispatch, so 1 hour is a generous margin, not a tight race.
     */
    private const MIN_WAIT_HOURS = 1;

    public function handle(): int
    {
        $candidates = Lead::query()
            ->where('recommendation_offer_key', '!=', OfferKey::GbpAudit->value)
            ->whereNotNull('recommendation_generated_at')
            ->where('recommendation_generated_at', '<=', now()->subHours(self::MIN_WAIT_HOURS))
            ->whereNull('recommendation_notified_at')
            ->whereNull('recommendation_retry_attempted_at')
            ->get();

        $retried = 0;
        $skippedEngaged = 0;

        foreach ($candidates as $lead) {
            // A human is already handling this lead — an automated "here's
            // your recommendation" message would be redundant/confusing on
            // top of a live conversation. Same guard every other first-touch
            // job in this family uses (Lead::hasStaffEngagementSince()).
            if ($lead->hasStaffEngagementSince($lead->recommendation_generated_at)) {
                $skippedEngaged++;

                continue;
            }

            // Marked BEFORE dispatching, and regardless of whether this
            // retry itself succeeds — this command must never give a lead a
            // second automated attempt, even if the retry also fails.
            $lead->forceFill(['recommendation_retry_attempted_at' => now()])->saveQuietly();

            SendOfferRecommendationReadyJob::dispatch($lead->id);

            Log::info('RetryStrandedOfferRecommendationMessages: retried a stranded recommendation-ready send', [
                'lead_id' => $lead->id,
            ]);

            $retried++;
        }

        $this->info("Retried {$retried} stranded recommendation message(s), skipped {$skippedEngaged} (staff already engaged), out of {$candidates->count()} candidate(s).");

        return self::SUCCESS;
    }
}
