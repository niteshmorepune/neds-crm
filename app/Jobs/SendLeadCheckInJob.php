<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Staff-triggered re-engagement WhatsApp send — "Send WhatsApp check-in" on
 * a lead's own page (LeadController::sendCheckIn()), for a lead whose
 * WhatsApp session window has already closed and who's gone quiet. Unlike
 * SendLeadWelcomeMessageJob (automatic, fires once, right at creation),
 * this is manual and re-sendable — the controller applies a soft 24h
 * cooldown via Lead.last_checkin_sent_at, this job itself has no
 * idempotency guard.
 *
 * Same wadesk.in POST /api/send-template contract as the rest of this job
 * family — no-ops (logs, never throws) until wadesk config and the
 * template name are set.
 */
class SendLeadCheckInJob implements ShouldQueue
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
        $templateName = (string) config('services.wadesk.lead_checkin_template_name');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $lead = Lead::find($this->leadId);

        if ($lead === null || blank($lead->phone)) {
            return;
        }

        $digits = Phone::digits($lead->phone);

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/send-template", [
                    'phone' => $digits,
                    'businessNumber' => $marketingNumber,
                    'templateName' => $templateName,
                    // The approved template body is bilingual (English then Hindi),
                    // so {{1}}/{{2}} and {{3}}/{{4}} repeat the same name/service.
                    'variables' => [
                        $lead->name ?: 'there',
                        $lead->service?->name ?? 'your enquiry',
                        $lead->name ?: 'there',
                        $lead->service?->name ?? 'your enquiry',
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('SendLeadCheckInJob: wadesk.in returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $lead->forceFill(['last_checkin_sent_at' => now()])->saveQuietly();

            if ($response->json('skipped') !== true) {
                // wadesk_message_id lets Api\WadeskMessageStatusController find
                // this lead back later if Meta's own async status webhook
                // reports this send FAILED after wadesk.in already accepted it
                // here (e.g. the "healthy ecosystem engagement" pacing
                // throttle, error 131049) -- see that controller's docblock.
                $lead->forceFill(['checkin_wadesk_id' => $response->json('messageId')])->saveQuietly();

                $lead->notes()->create([
                    'user_id' => null,
                    'body' => '✨ Re-engagement check-in sent via WhatsApp.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendLeadCheckInJob: HTTP call to wadesk.in failed', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
