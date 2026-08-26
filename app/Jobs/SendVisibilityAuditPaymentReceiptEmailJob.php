<?php

namespace App\Jobs;

use App\Mail\VisibilityAuditPaymentReceived;
use App\Models\VisibilityAuditPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the email half of the Visibility Audit "thank you for paying"
 * confirmation — the sibling of SendVisibilityAuditPaymentConfirmationJob
 * (WhatsApp), dispatched alongside it from RecordVisibilityAuditPurchase.
 * Kept as its own job, not inlined, for the same reason every other
 * external-notification side effect in this flow gets its own job: one
 * channel failing (email down, wadesk down) must never block or retry the
 * other. Never throws — an email failure must not affect the payment
 * record itself, same discipline as RazorpayPaymentRecorder::sendReceipt().
 */
class SendVisibilityAuditPaymentReceiptEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $purchaseId) {}

    public function handle(): void
    {
        $purchase = VisibilityAuditPurchase::find($this->purchaseId);

        if ($purchase === null || blank($purchase->payer_email)) {
            return;
        }

        try {
            Mail::to($purchase->payer_email)->send(new VisibilityAuditPaymentReceived($purchase));
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditPaymentReceiptEmailJob: send failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
