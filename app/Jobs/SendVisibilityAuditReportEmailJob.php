<?php

namespace App\Jobs;

use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Mail\VisibilityAuditReportEmail;
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
 * Step 4 of the post-payment conversion pipeline — the email half of
 * "Send Audit Report", dispatched once per click from
 * LeadController::sendVisibilityAuditReport(), never from a scheduled
 * sweep (unlike every earlier job in this pipeline) — a deliberate resend
 * is a valid, expected use, so there is no self-guard/idempotency column
 * here at all.
 */
class SendVisibilityAuditReportEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $purchaseId) {}

    public function handle(): void
    {
        $purchase = VisibilityAuditPurchase::find($this->purchaseId);

        if ($purchase === null || blank($purchase->payer_email) || $purchase->reportAttachment() === null) {
            return;
        }

        try {
            Mail::to($purchase->payer_email)->send(new VisibilityAuditReportEmail($purchase));
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditReportEmailJob: send failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);

            $this->logTouch($purchase, false, ['error' => $e->getMessage()]);

            return;
        }

        $this->logTouch($purchase, true, null);
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

        VisibilityAuditTouch::logSend($purchase->lead_id, VisibilityAuditTouchType::ReportSent, $success, $meta, VisibilityAuditTouchChannel::AiEmail);
    }
}
