<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stages a Lead in wadesk.in the moment it's created or reassigned, via
 * wadesk.in's POST /api/leads/sync — so the Sales owner opens wadesk and
 * the lead is already sitting there (Contact + Conversation on the
 * Marketing line, assigned to them), instead of re-typing name/number.
 *
 * Sends no message and makes no Meta API call — this only stages internal
 * wadesk.in state. Safe to call repeatedly (idempotent by phone+line on
 * wadesk.in's side); fired again on every owner_id change so reassignment
 * stays in sync.
 *
 * No-ops (logs, never throws) whenever wadesk config or the lead's phone is
 * absent. Same failure-tolerance discipline as SendWhatsappHandoffMessageJob
 * — a wadesk.in outage must never block lead creation/assignment.
 */
class SyncLeadToWadeskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $leadId) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');
        $marketingNumber = (string) config('services.wadesk.marketing_number');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber) {
            return;
        }

        $lead = Lead::find($this->leadId);

        if ($lead === null || blank($lead->phone)) {
            return;
        }

        // wadesk.in stores contact phone digits-only, no leading "+" — matches
        // SendWhatsappHandoffMessageJob's / WhatsappWebhookController's own normalization.
        $digits = preg_replace('/\D/', '', $lead->phone);

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/leads/sync", [
                    'phone' => $digits,
                    'name' => $lead->name,
                    'businessNumber' => $marketingNumber,
                    'agentEmail' => $lead->owner?->email,
                ]);

            if (! $response->successful()) {
                Log::warning('SyncLeadToWadeskJob: wadesk.in returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $conversationId = $response->json('conversationId');

            if (filled($conversationId) && blank($lead->whatsapp_conversation_id)) {
                $lead->whatsapp_conversation_id = $conversationId;
                $lead->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning('SyncLeadToWadeskJob: HTTP call to wadesk.in failed', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
