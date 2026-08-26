<?php

namespace App\Console\Commands;

use App\Jobs\SendVisibilityAuditInProgressEmailJob;
use App\Jobs\SendVisibilityAuditInProgressJob;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Console\Command;

/**
 * Step 2 of the post-payment conversion pipeline: a short while after
 * paying, dispatch the "audit in progress" reassurance on whichever
 * channel(s) a purchase hasn't received it on yet — each job gates itself
 * on its own idempotency column, so re-dispatching a purchase that already
 * got one channel is a safe no-op for that channel.
 */
class SendVisibilityAuditInProgressNudges extends Command
{
    protected $signature = 'app:send-visibility-audit-in-progress-nudges';

    protected $description = 'Dispatch the "audit in progress" WhatsApp + email nudge for any Visibility Audit purchase still missing one after 30 minutes (run every 15 minutes via scheduler).';

    private const WAIT_MINUTES = 30;

    public function handle(VisibilityAuditFunnelMetrics $metrics): int
    {
        $sent = 0;

        foreach ($metrics->pendingInProgressNudges(now()->subMinutes(self::WAIT_MINUTES)) as $purchase) {
            SendVisibilityAuditInProgressJob::dispatch($purchase->id);
            SendVisibilityAuditInProgressEmailJob::dispatch($purchase->id);
            $sent++;
        }

        $this->info("Dispatched Visibility Audit in-progress nudge job(s) for {$sent} purchase(s).");

        return self::SUCCESS;
    }
}
