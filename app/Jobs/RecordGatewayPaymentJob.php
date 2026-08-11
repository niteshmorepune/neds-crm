<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\RazorpayPaymentRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async, reliable path for recording a captured Razorpay payment — dispatched
 * from the webhook (the source of truth). The sync portal verify endpoint
 * calls RazorpayPaymentRecorder directly for immediate UX; this job's own
 * call is a no-op via the recorder's gateway_payment_id idempotency guard if
 * that path already recorded it. Queue driver: database (Hostinger — no
 * Redis/Horizon), same shape as ProvisionClientExternallyJob.
 */
class RecordGatewayPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $invoiceId,
        public string $orderId,
        public string $paymentId,
        public int $amountPaise,
    ) {}

    public function handle(RazorpayPaymentRecorder $recorder): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if ($invoice === null) {
            Log::warning('Razorpay webhook: invoice not found', [
                'invoice_id' => $this->invoiceId,
                'payment_id' => $this->paymentId,
            ]);

            return;
        }

        try {
            $recorder->record($invoice, $this->orderId, $this->paymentId, $this->amountPaise);
        } catch (\Throwable $e) {
            Log::warning('Razorpay webhook payment recording failed', [
                'invoice_id' => $this->invoiceId,
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
