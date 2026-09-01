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
 *
 * Give-up-after-MAX_ATTEMPTS guard: real incident, 2026-08-27 — the
 * configured WhatsApp template was briefly unapproved, and this job (fired
 * every 15 min by SendVisibilityAuditInProgressNudges, forever, since a
 * failed attempt never sets in_progress_notified_at) kept re-attempting the
 * same handful of purchases for over an hour before the template got
 * approved and it self-resolved. This time it was quick; a longer-lived
 * misconfiguration would have retried indefinitely. Once
 * in_progress_whatsapp_attempts reaches MAX_ATTEMPTS, this job stops
 * attempting that purchase's WhatsApp send entirely (in_progress_whatsapp_gave_up_at
 * is set, and VisibilityAuditFunnelMetrics::pendingInProgressNudges() excludes
 * it going forward) — the failed VisibilityAuditTouch rows already logged
 * along the way remain visible on the Message Log for manual follow-up,
 * same "relabel/surface a real failure, don't hide it" convention as
 * everywhere else in this app. The email channel (SendVisibilityAuditInProgressEmailJob)
 * tracks its own attempts/give-up independently.
 */
class SendVisibilityAuditInProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    private const MAX_ATTEMPTS = 5;

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

        if ($purchase->in_progress_whatsapp_gave_up_at !== null) {
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

                $this->recordFailedAttempt($purchase, ['status' => $response->status()]);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditInProgressJob: HTTP call to wadesk.in failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);

            $this->recordFailedAttempt($purchase, ['error' => $e->getMessage()]);

            return;
        }

        $this->logTouch($purchase, true, ['template' => $templateName, 'wadesk_message_id' => $response->json('messageId')]);

        $purchase->forceFill(['in_progress_notified_at' => now()])->saveQuietly();
    }

    /**
     * Logs the failed touch, then increments the attempt counter and — once
     * it reaches MAX_ATTEMPTS — sets in_progress_whatsapp_gave_up_at so
     * pendingInProgressNudges() stops surfacing this purchase's WhatsApp
     * channel, and logs a distinct warning naming the purchase so it's
     * findable rather than just blending into the routine per-attempt noise.
     */
    private function recordFailedAttempt(VisibilityAuditPurchase $purchase, array $meta): void
    {
        $this->logTouch($purchase, false, $meta);

        $attempts = $purchase->in_progress_whatsapp_attempts + 1;
        $values = ['in_progress_whatsapp_attempts' => $attempts];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $values['in_progress_whatsapp_gave_up_at'] = now();

            Log::warning('SendVisibilityAuditInProgressJob: giving up after max attempts, needs manual follow-up', [
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
    private function logTouch(VisibilityAuditPurchase $purchase, bool $success, array $meta): void
    {
        if ($purchase->lead_id === null) {
            return;
        }

        VisibilityAuditTouch::logSend($purchase->lead_id, VisibilityAuditTouchType::AuditInProgress, $success, $meta);
    }
}
