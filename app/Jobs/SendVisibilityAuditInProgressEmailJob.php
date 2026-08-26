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
 */
class SendVisibilityAuditInProgressEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $purchaseId) {}

    public function handle(): void
    {
        $purchase = VisibilityAuditPurchase::find($this->purchaseId);

        if ($purchase === null || blank($purchase->payer_email) || $purchase->in_progress_notified_email_at !== null) {
            return;
        }

        try {
            Mail::to($purchase->payer_email)->send(new VisibilityAuditInProgressEmail($purchase));
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditInProgressEmailJob: send failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);

            $this->logTouch($purchase, false, ['error' => $e->getMessage()]);

            return;
        }

        $this->logTouch($purchase, true, null);

        $purchase->forceFill(['in_progress_notified_email_at' => now()])->saveQuietly();
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
