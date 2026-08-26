<?php

namespace App\Jobs;

use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Mail\VisibilityAuditFirstInviteEmail;
use App\Models\Lead;
use App\Models\VisibilityAuditTouch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email sibling of SendVisibilityAuditFirstInviteJob (WhatsApp) — dispatched
 * from the same call sites (LeadObserver::sendVisibilityAuditInviteIfEligible(),
 * SendVisibilityAuditFirstInviteSweep), its own job so an email failure can
 * never block/retry the WhatsApp send or vice versa. Unlike the WhatsApp
 * job, needs no wadesk config/Meta template approval — the only gate is
 * whether the lead has an email at all.
 *
 * Idempotent on Lead.visibility_audit_invite_emailed_at — a DEDICATED
 * column, not the WhatsApp job's visibility_audit_invited_at, so a lead
 * whose WhatsApp invite already went out still gets its own independent
 * email attempt (and vice versa).
 *
 * Known small gap, accepted rather than over-built: SendVisibilityAuditFirstInviteSweep's
 * re-check query is keyed on visibility_audit_invited_at (the WhatsApp
 * column) — so a lead whose WhatsApp invite succeeded but whose email
 * failed on the first attempt won't be re-swept for email specifically.
 * The job's own 3 tries/60s backoff still covers ordinary transient
 * failures; a lasting SMTP outage would need a manual re-dispatch.
 */
class SendVisibilityAuditFirstInviteEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $leadId) {}

    public function handle(): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead === null || blank($lead->email) || $lead->visibility_audit_invite_emailed_at !== null) {
            return;
        }

        // Same race-condition guard as the WhatsApp job's own docblock: real
        // time passes on the database queue before this handle() runs, long
        // enough for the lead to independently pay in between.
        if ($lead->visibilityAuditPurchases()->exists()) {
            return;
        }

        try {
            Mail::to($lead->email)->send(new VisibilityAuditFirstInviteEmail($lead));
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditFirstInviteEmailJob: send failed', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);

            VisibilityAuditTouch::logSend($this->leadId, VisibilityAuditTouchType::FirstInvite, false, ['error' => $e->getMessage()], VisibilityAuditTouchChannel::AiEmail);

            return;
        }

        VisibilityAuditTouch::logSend($this->leadId, VisibilityAuditTouchType::FirstInvite, true, null, VisibilityAuditTouchChannel::AiEmail);

        $lead->forceFill(['visibility_audit_invite_emailed_at' => now()])->saveQuietly();
    }
}
