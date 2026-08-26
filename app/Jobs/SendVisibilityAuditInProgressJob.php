<?php

namespace App\Jobs;

use App\Enums\VisibilityAuditTouchType;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Step 2 of the post-payment conversion pipeline: a short while after
 * paying (see SendVisibilityAuditInProgressNudges' own WAIT_MINUTES), a
 * WhatsApp reassurance that work has actually started — distinct from the
 * immediate payment-received thank-you (SendVisibilityAuditPaymentConfirmationJob).
 * Same wadesk.in POST /api/send-template contract, same no-op-until-
 * configured/never-throw discipline, and the same "only a lead-attributed
 * purchase gets a touch row" scope as that job.
 *
 * Idempotent on VisibilityAuditPurchase.in_progress_notified_at — a
 * DEDICATED column, separate from in_progress_notified_email_at (the email
 * sibling job's own column), so the two channels never block or race each
 * other.
 */
class SendVisibilityAuditInProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $purchaseId) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');
        $marketingNumber = (string) config('services.wadesk.marketing_number');
        $templateName = (string) config('services.wadesk.visibility_audit_in_progress_template_name');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $purchase = VisibilityAuditPurchase::find($this->purchaseId);

        if ($purchase === null || blank($purchase->payer_phone) || $purchase->in_progress_notified_at !== null) {
            return;
        }

        $digits = Phone::digits($purchase->payer_phone);

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/send-template", [
                    'phone' => $digits,
                    'businessNumber' => $marketingNumber,
                    'templateName' => $templateName,
                    'variables' => [$purchase->payer_name ?: 'there'],
                ]);

            if (! $response->successful()) {
                Log::warning('SendVisibilityAuditInProgressJob: wadesk.in returned non-2xx', [
                    'purchase_id' => $this->purchaseId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->logTouch($purchase, false, ['status' => $response->status()]);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditInProgressJob: HTTP call to wadesk.in failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);

            $this->logTouch($purchase, false, ['error' => $e->getMessage()]);

            return;
        }

        $this->logTouch($purchase, true, ['template' => $templateName, 'wadesk_message_id' => $response->json('messageId')]);

        $purchase->forceFill(['in_progress_notified_at' => now()])->saveQuietly();
    }

    /**
     * Only lead-attributed purchases get a touch row — same scope as
     * SendVisibilityAuditPaymentConfirmationJob's own logTouch().
     */
    private function logTouch(VisibilityAuditPurchase $purchase, bool $success, array $meta): void
    {
        if ($purchase->lead_id === null) {
            return;
        }

        VisibilityAuditTouch::logSend($purchase->lead_id, VisibilityAuditTouchType::AuditInProgress, $success, $meta);
    }
}
