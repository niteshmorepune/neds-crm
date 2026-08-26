<?php

namespace App\Console\Commands;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Jobs\SendVisibilityAuditRecoveryNudgeEmailJob;
use App\Jobs\SendVisibilityAuditRecoveryNudgeJob;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Console\Command;

class SendVisibilityAuditRecoveryNudges extends Command
{
    protected $signature = 'app:send-visibility-audit-recovery-nudges';

    protected $description = 'Dispatch one WhatsApp + one email recovery nudge per Lead stuck at the Visibility Audit landing page or checkout stage (run every 30 minutes via scheduler).';

    /**
     * Checkout is the closer-to-converting stage, so it gets a shorter wait
     * before nudging (catch them while still warm); landing gets a bit
     * longer since it's a softer signal. Both deliberately short of a full
     * day — the whole point is not depending on a human noticing.
     */
    private const CHECKOUT_WAIT_HOURS = 2;

    private const LANDING_WAIT_HOURS = 4;

    public function handle(VisibilityAuditFunnelMetrics $metrics): int
    {
        $sent = 0;

        foreach ($metrics->pendingCheckoutNudges(now()->subHours(self::CHECKOUT_WAIT_HOURS)) as $lead) {
            $eventId = $lead->visibilityAuditFunnelEvents->first()->id;
            SendVisibilityAuditRecoveryNudgeJob::dispatch($lead->id, $eventId, VisibilityAuditFunnelEventType::PaymentViewed);
            SendVisibilityAuditRecoveryNudgeEmailJob::dispatch($lead->id, $eventId, VisibilityAuditFunnelEventType::PaymentViewed);
            $sent++;
        }

        foreach ($metrics->pendingLandingNudges(now()->subHours(self::LANDING_WAIT_HOURS)) as $lead) {
            $eventId = $lead->visibilityAuditFunnelEvents->first()->id;
            SendVisibilityAuditRecoveryNudgeJob::dispatch($lead->id, $eventId, VisibilityAuditFunnelEventType::LandingViewed);
            SendVisibilityAuditRecoveryNudgeEmailJob::dispatch($lead->id, $eventId, VisibilityAuditFunnelEventType::LandingViewed);
            $sent++;
        }

        $this->info("Dispatched {$sent} Visibility Audit recovery nudge(s).");

        return self::SUCCESS;
    }
}
