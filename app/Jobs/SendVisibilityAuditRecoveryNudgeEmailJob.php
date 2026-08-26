<?php

namespace App\Jobs;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Mail\VisibilityAuditRecoveryNudgeEmail;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditTouch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email sibling of SendVisibilityAuditRecoveryNudgeJob (WhatsApp) —
 * dispatched from the same SendVisibilityAuditRecoveryNudges command loop,
 * its own job for the same channel-independence reason as
 * SendVisibilityAuditFirstInviteEmailJob. No wadesk config/Meta template
 * approval needed.
 *
 * Idempotent on VisibilityAuditFunnelEvent.nudged_email_at — a DEDICATED
 * column, separate from the WhatsApp job's nudged_at, so the two channels
 * never block or race each other.
 *
 * Known small gap, accepted rather than over-built (same tradeoff as
 * SendVisibilityAuditFirstInviteEmailJob): pendingCheckoutNudges()/
 * pendingLandingNudges() gate on nudged_at (the WhatsApp column), so once
 * the WhatsApp nudge succeeds for an event, later command runs stop
 * re-offering it — an email whose 3 tries all fail within this one
 * dispatch has no further scheduled retry. Both jobs are still dispatched
 * together on every run that DOES match, so this only bites a lasting
 * SMTP outage spanning all 3 attempts.
 */
class SendVisibilityAuditRecoveryNudgeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $leadId,
        public int $funnelEventId,
        public VisibilityAuditFunnelEventType $stage,
    ) {}

    public function handle(): void
    {
        $event = VisibilityAuditFunnelEvent::find($this->funnelEventId);
        if ($event === null || $event->nudged_email_at !== null) {
            return; // already emailed (or deleted) — never double-send.
        }

        $lead = Lead::find($this->leadId);
        if ($lead === null || blank($lead->email)) {
            return;
        }

        // Same re-check-at-send-time reasoning as SendVisibilityAuditRecoveryNudgeJob's
        // own docblock — real time passes on the database queue before
        // handle() runs.
        if ($lead->visibilityAuditPurchases()->exists()) {
            return;
        }

        if ($lead->hasStaffWhatsappReplySince($event->created_at)) {
            return;
        }

        try {
            Mail::to($lead->email)->send(new VisibilityAuditRecoveryNudgeEmail($lead, $this->stage));
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditRecoveryNudgeEmailJob: send failed', [
                'lead_id' => $this->leadId,
                'stage' => $this->stage->value,
                'error' => $e->getMessage(),
            ]);

            VisibilityAuditTouch::logSend($this->leadId, $this->touchType(), false, ['error' => $e->getMessage()], VisibilityAuditTouchChannel::AiEmail);

            return;
        }

        VisibilityAuditTouch::logSend($this->leadId, $this->touchType(), true, null, VisibilityAuditTouchChannel::AiEmail);

        $event->update(['nudged_email_at' => now()]);
    }

    private function touchType(): VisibilityAuditTouchType
    {
        return $this->stage === VisibilityAuditFunnelEventType::LandingViewed
            ? VisibilityAuditTouchType::RecoveryNudgeLanding
            : VisibilityAuditTouchType::RecoveryNudgeCheckout;
    }
}
