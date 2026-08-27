<?php

namespace App\Jobs;

use App\Models\VisibilityAuditPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts an unmistakable "money just landed" alert to the same shared
 * Telegram group SendTelegramLeadAlertJob already uses — dispatched from
 * RecordVisibilityAuditPurchase for EVERY purchase, whether it matched an
 * existing Lead, created a brand new one, or found no phone to match at all.
 *
 * Why this exists (real incident, 2026-08-27): a purchase from a payer with
 * no prior Meta-Ads history auto-creates a bare Lead via
 * RecordVisibilityAuditPurchase::createLead() — that Lead's own new-lead
 * Telegram alert (SendTelegramLeadAlertJob) says only "New lead: {name} /
 * Source: Other", indistinguishable from any random inbound lead. Two real
 * ₹120 purchases got deleted as noise within minutes because nothing in
 * that alert hinted at the payment. Worse, a purchase that matches an
 * ALREADY-EXISTING Lead (RecordVisibilityAuditPurchase::attachToExistingLead())
 * triggers no Telegram alert at all, since no new Lead row is created —
 * that path had zero staff-facing signal of any kind.
 *
 * No-ops (logs, never throws) until both TELEGRAM_BOT_TOKEN and
 * TELEGRAM_CHAT_ID are set — same inert-until-configured contract as
 * SendTelegramLeadAlertJob (already live in production as of this job).
 */
class SendVisibilityAuditPurchaseAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $purchaseId) {}

    public function handle(): void
    {
        $botToken = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.chat_id');

        if (! $botToken || ! $chatId) {
            return;
        }

        // Read fresh, not the dispatching request's copy — this is queued
        // immediately after creation, before lead matching runs, so lead_id
        // is very likely still null on any in-memory instance at this point.
        $purchase = VisibilityAuditPurchase::with('lead')->find($this->purchaseId);

        if ($purchase === null) {
            return;
        }

        $amount = number_format($purchase->amount_paise / 100);
        $tier = $purchase->tier?->label() ?? 'Visibility Audit';
        $name = $purchase->payer_name ?: 'Unknown';
        $phone = $purchase->payer_phone ?: 'not provided';

        $text = "💰 {$tier} paid — ₹{$amount}\n{$name} · {$phone}";

        if ($purchase->lead) {
            $text .= "\n".route('leads.show', $purchase->lead_id);
        } else {
            $text .= "\n(no phone to match a Lead — check the Visibility Audit purchase directly)";
        }

        try {
            $response = Http::timeout(15)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);

            if (! $response->successful()) {
                Log::warning('SendVisibilityAuditPurchaseAlertJob: Telegram API returned non-2xx', [
                    'purchase_id' => $this->purchaseId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditPurchaseAlertJob: HTTP call to Telegram failed', [
                'purchase_id' => $this->purchaseId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
