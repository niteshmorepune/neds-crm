<?php

namespace App\Console\Commands;

use App\Jobs\SendVisibilityAuditFirstInviteEmailJob;
use App\Jobs\SendVisibilityAuditFirstInviteJob;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Console\Command;

/**
 * Safety net behind LeadObserver::sendVisibilityAuditInviteIfEligible() —
 * that dispatch happens once, at the moment a Lead becomes eligible, and
 * SendVisibilityAuditFirstInviteJob never throws on a no-op (missing config,
 * a transient HTTP failure), so nothing ever retries it on its own. Without
 * this sweep, an eligible lead whose one-shot dispatch silently no-op'd
 * stays stuck until a human notices the "Not yet invited" dashboard callout
 * — real incident, 2026-08-21 (3 leads sat uninvited for ~3 days).
 */
class SendVisibilityAuditFirstInviteSweep extends Command
{
    protected $signature = 'app:send-visibility-audit-first-invite-sweep';

    protected $description = 'Re-dispatch the Visibility Audit first-invite jobs (WhatsApp + email) for any eligible Lead still not invited after 10 minutes (run every 10 minutes via scheduler).';

    private const WAIT_MINUTES = 10;

    public function handle(VisibilityAuditFunnelMetrics $metrics): int
    {
        $sent = 0;

        foreach ($metrics->pendingFirstInvites(now()->subMinutes(self::WAIT_MINUTES)) as $lead) {
            SendVisibilityAuditFirstInviteJob::dispatch($lead->id);
            SendVisibilityAuditFirstInviteEmailJob::dispatch($lead->id);
            $sent++;
        }

        $this->info("Dispatched {$sent} Visibility Audit first-invite sweep job(s).");

        return self::SUCCESS;
    }
}
