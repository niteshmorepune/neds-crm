<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Jobs\SendLeadCheckInJob;
use App\Models\Lead;
use Illuminate\Console\Command;

/**
 * The generic (non-GMB) counterpart to SendOfferFunnelRecoveryNudges:
 * GMB-tagged leads already get an automatic funnel-stall nudge if they stop
 * progressing, but every OTHER Meta Ads lead depended entirely on a
 * telecaller remembering to click "Send WhatsApp check-in" by hand. This
 * closes that gap with one automatic check-in (reusing the existing manual
 * job/template as-is) once Lead::WELCOME_FOLLOWUP_WAIT_HOURS pass with no
 * reply from the lead, staff, or the after-hours AI assistant.
 *
 * Fires at most once per lead -- last_checkin_sent_at (set by the job
 * itself, shared with the manual button) is the guard, so this can never
 * double-send on top of a staff member's own manual click either.
 */
class SendLeadWelcomeFollowUps extends Command
{
    protected $signature = 'app:send-lead-welcome-followups';

    protected $description = 'Dispatch one automatic WhatsApp check-in per Meta Ads lead whose welcome message got no reply within the wait window (run every 30 minutes via scheduler).';

    public function handle(): int
    {
        $leads = Lead::query()
            ->whereNotNull('welcome_message_sent_at')
            ->where('welcome_message_sent_at', '<=', now()->subHours(Lead::WELCOME_FOLLOWUP_WAIT_HOURS))
            ->whereNull('last_checkin_sent_at')
            ->whereIn('status', LeadStatus::openValues())
            ->with([
                'notes:id,notable_id,notable_type,user_id,body',
                'callLogs:id,callable_id,callable_type,outcome',
            ])
            ->get()
            ->filter(fn (Lead $lead) => $lead->isAwaitingWelcomeReply());

        $leads->each(fn (Lead $lead) => SendLeadCheckInJob::dispatch($lead->id));

        $this->info("Dispatched {$leads->count()} lead welcome follow-up check-in(s).");

        return self::SUCCESS;
    }
}
