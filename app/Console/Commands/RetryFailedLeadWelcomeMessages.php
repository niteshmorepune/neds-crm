<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Jobs\SendLeadWelcomeMessageJob;
use App\Models\Lead;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Console\Command;

/**
 * Real incident, 2026-09-10: Meta's own "healthy ecosystem engagement"
 * pacing throttle (error 131049) rejected several lead_welcome sends.
 * WadeskMessageStatusController already detects this and resets
 * welcome_message_sent_at/welcome_message_wadesk_id to null, but nothing
 * downstream ever re-attempted the send — a throttled lead just sat there,
 * with no automation scheduled to ever reach it again (SendLeadCheckInJob's
 * own follow-up command only fires once a welcome has actually gone out).
 *
 * This closes that gap: re-dispatches SendLeadWelcomeMessageJob for any lead
 * whose welcome genuinely failed before (Lead::isEligibleForWelcomeMessageRetry()
 * — a real prior failure note, not just "never attempted yet", which stays
 * LeadObserver's job), waits WELCOME_RETRY_WAIT_HOURS between attempts so a
 * live throttle isn't immediately re-hammered, and gives up for good after
 * WELCOME_RETRY_MAX_ATTEMPTS failures — leaving that lead to a staff
 * member's own manual "Send WhatsApp check-in" click, same as any lead this
 * automation was never going to reach.
 */
class RetryFailedLeadWelcomeMessages extends Command
{
    protected $signature = 'app:retry-failed-lead-welcome-messages';

    protected $description = 'Re-attempt the automated WhatsApp welcome message for a Meta Ads lead whose previous send was rejected by Meta (run every 30 minutes via scheduler).';

    public function __construct(private readonly VisibilityAuditFunnelMetrics $visibilityAuditFunnelMetrics)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $gmbServiceId = $this->visibilityAuditFunnelMetrics->gmbServiceId();

        $candidates = Lead::query()
            ->whereNull('welcome_message_sent_at')
            ->whereNull('last_checkin_sent_at')
            ->whereNotNull('meta_leadgen_id')
            ->when($gmbServiceId, fn ($q) => $q->where('service_id', '!=', $gmbServiceId))
            ->whereIn('status', LeadStatus::openValues())
            ->with(['notes:id,notable_id,notable_type,body,created_at'])
            ->get();

        $retrying = $candidates->filter(fn (Lead $lead) => $lead->isEligibleForWelcomeMessageRetry());
        $newlyGaveUp = $candidates->filter(fn (Lead $lead) => $lead->needsWelcomeRetryGiveUpNote());

        $newlyGaveUp->each(function (Lead $lead) {
            $lead->notes()->create([
                'user_id' => null,
                'body' => '⚠️ Automated welcome message retry limit reached ('.Lead::WELCOME_RETRY_MAX_ATTEMPTS.' failed attempts) — try "Send WhatsApp check-in" manually.',
            ]);
        });

        $retrying->each(fn (Lead $lead) => SendLeadWelcomeMessageJob::dispatch($lead->id));

        $this->info("Retried {$retrying->count()} lead welcome message(s), gave up on {$newlyGaveUp->count()}.");

        return self::SUCCESS;
    }
}
