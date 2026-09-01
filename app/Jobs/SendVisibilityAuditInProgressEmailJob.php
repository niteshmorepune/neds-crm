<?php

namespace App\Jobs;

use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Mail\VisibilityAuditInProgressEmail;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email sibling of SendVisibilityAuditInProgressJob (WhatsApp) — its own
 * job for the same channel-independence reason as every other *EmailJob in
 * this pipeline. No wadesk config/Meta template approval needed — the only
 * gate is whether the purchase has a payer_email.
 *
 * Idempotent on VisibilityAuditPurchase.in_progress_notified_email_at — a
 * DEDICATED column, separate from the WhatsApp job's
 * in_progress_notified_at.
 *
 * Give-up-after-MAX_ATTEMPTS guard: same reasoning and shape as the
 * WhatsApp job's own (see its docblock for the real incident this closes)
 * — its own attempts/give-up columns, tracked independently so an email
 * channel that's permanently failing (e.g. a malformed payer_email) never
 * blocks or is blocked by the WhatsApp channel's own retry state.
 */
class SendVisibilityAuditInProgressEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    private const MAX_ATTEMPTS = 5;

    public function __construct(public int $purchaseId) {}

    public function handle(): void
    {
        $purchase = VisibilityAuditPurchase::find($this->purchaseId);

        if ($purchase === null || blank($purchase->payer_email) || $purchase->in_progress_notified_email_at !== null) {
            return;
        }

        if ($purchase->in_progress_email_gave_up_at !== null) {
            return;
        }

        try {
            Mail::to($purchase->payer_email)->send(new VisibilityAuditInProgressEmail($purchase));
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditInProgressEmailJob: send failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);

            $this->recordFailedAttempt($purchase, ['error' => $e->getMessage()]);

            return;
        }

        $this->logTouch($purchase, true, null);

        $purchase->forceFill(['in_progress_notified_email_at' => now()])->saveQuietly();
    }

    /**
     * Same shape as SendVisibilityAuditInProgressJob::recordFailedAttempt()
     * — see that method's docblock.
     */
    private function recordFailedAttempt(VisibilityAuditPurchase $purchase, array $meta): void
    {
        $this->logTouch($purchase, false, $meta);

        $attempts = $purchase->in_progress_email_attempts + 1;
        $values = ['in_progress_email_attempts' => $attempts];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $values['in_progress_email_gave_up_at'] = now();

            Log::warning('SendVisibilityAuditInProgressEmailJob: giving up after max attempts, needs manual follow-up', [
                'purchase_id' => $this->purchaseId,
                'attempts' => $attempts,
            ]);
        }

        $purchase->forceFill($values)->saveQuietly();
    }

    /**
     * Only lead-attributed purchases get a touch row — same scope as
     * SendVisibilityAuditPaymentConfirmationJob's own logTouch().
     */
    private function logTouch(VisibilityAuditPurchase $purchase, bool $success, ?array $meta): void
    {
        if ($purchase->lead_id === null) {
            return;
        }

        VisibilityAuditTouch::logSend($purchase->lead_id, VisibilityAuditTouchType::AuditInProgress, $success, $meta, VisibilityAuditTouchChannel::AiEmail);
    }
}
