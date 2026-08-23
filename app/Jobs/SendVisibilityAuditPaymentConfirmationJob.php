<?php

namespace App\Jobs;

use App\Enums\VisibilityAuditTouchType;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a WhatsApp payment-confirmation template to whoever just paid for a
 * Visibility Audit offer tier (/offers/visibility-audit), via wadesk.in's
 * POST /api/send-template on the Marketing line — mirrors
 * SendWhatsappHandoffMessageJob's shape exactly, just a different template
 * and business number. The payer may never have messaged this number
 * before (they came in via a Razorpay Payment Page, not a WhatsApp thread),
 * so /api/send-template (not the ordinary conversation-attached /api/send)
 * is the right call, same reasoning as the handoff job.
 *
 * No-ops (logs, never throws) whenever wadesk config or the template name is
 * unset — the template must be submitted and approved in Meta Business
 * Manager first (see config/services.php's suggested body); this job is
 * safe to ship before that happens and starts working the moment
 * WADESK_VISIBILITY_AUDIT_TEMPLATE_NAME is set, no code change needed.
 */
class SendVisibilityAuditPaymentConfirmationJob implements ShouldQueue
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
        $templateName = (string) config('services.wadesk.visibility_audit_payment_template_name');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $purchase = VisibilityAuditPurchase::find($this->purchaseId);

        if ($purchase === null || blank($purchase->payer_phone)) {
            return;
        }

        // wadesk.in stores contact phone digits-only, no leading "+".
        $digits = Phone::digits($purchase->payer_phone);
        $tierSummary = ($purchase->tier?->label() ?? 'Visibility Audit').' ('.Money::format($purchase->amount_paise).')';

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/send-template", [
                    'phone' => $digits,
                    'businessNumber' => $marketingNumber,
                    'templateName' => $templateName,
                    // Matches the template's {{1}}/{{2}} — see the suggested
                    // body documented alongside the config value.
                    'variables' => [$purchase->payer_name ?: 'there', $tierSummary],
                ]);

            if (! $response->successful()) {
                Log::warning('SendVisibilityAuditPaymentConfirmationJob: wadesk.in returned non-2xx', [
                    'purchase_id' => $this->purchaseId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->logTouch($purchase, false, ['status' => $response->status()]);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditPaymentConfirmationJob: HTTP call to wadesk.in failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);

            $this->logTouch($purchase, false, ['error' => $e->getMessage()]);

            return;
        }

        // wadesk_message_id — see SendVisibilityAuditFirstInviteJob's own
        // comment on this same field for why it's captured.
        $this->logTouch($purchase, true, ['template' => $templateName, 'wadesk_message_id' => $response->json('messageId')]);
    }

    /**
     * Only lead-attributed purchases get a touch row — an anonymous
     * checkout (never clicked through a tracked/attributed link) has no
     * Lead to log the touch against, same "lead-attributed only" scope as
     * every other VisibilityAuditFunnelMetrics figure.
     */
    private function logTouch(VisibilityAuditPurchase $purchase, bool $success, array $meta): void
    {
        if ($purchase->lead_id === null) {
            return;
        }

        VisibilityAuditTouch::logSend($purchase->lead_id, VisibilityAuditTouchType::PaymentConfirmation, $success, $meta);
    }
}
