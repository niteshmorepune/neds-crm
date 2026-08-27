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
 * Step 4 of the post-payment conversion pipeline — the WhatsApp half of
 * "Send Audit Report". Unlike every earlier WhatsApp job here, the button
 * doesn't link to a lead-tracking page — it links to
 * VisibilityAuditPurchase::reportUrl(), the permanent report-view link
 * (wadesk.in's template contract has no document-header support, so the
 * actual file only ever travels as a real email attachment — see
 * SendVisibilityAuditReportEmailJob). Dispatched once per click, never
 * from a scheduled sweep — no self-guard/idempotency column, a deliberate
 * resend is valid.
 */
class SendVisibilityAuditReportJob implements ShouldQueue
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
        $templateName = (string) config('services.wadesk.visibility_audit_report_template_name');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $purchase = VisibilityAuditPurchase::find($this->purchaseId);

        if ($purchase === null || blank($purchase->payer_phone) || $purchase->reportAttachment() === null) {
            return;
        }

        $digits = Phone::digits($purchase->payer_phone);
        // reportUrl() lazily generates+persists report_token on first call —
        // ensures the button's dynamic value exists before we send it.
        $purchase->reportUrl();

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/send-template", [
                    'phone' => $digits,
                    'businessNumber' => $marketingNumber,
                    'templateName' => $templateName,
                    'variables' => [$purchase->payer_name ?: 'there'],
                    'buttonUrlParam' => $purchase->report_token,
                ]);

            if (! $response->successful()) {
                Log::warning('SendVisibilityAuditReportJob: wadesk.in returned non-2xx', [
                    'purchase_id' => $this->purchaseId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->logTouch($purchase, false, ['status' => $response->status()]);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditReportJob: HTTP call to wadesk.in failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);

            $this->logTouch($purchase, false, ['error' => $e->getMessage()]);

            return;
        }

        $this->logTouch($purchase, true, ['template' => $templateName, 'wadesk_message_id' => $response->json('messageId')]);
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

        VisibilityAuditTouch::logSend($purchase->lead_id, VisibilityAuditTouchType::ReportSent, $success, $meta);
    }
}
