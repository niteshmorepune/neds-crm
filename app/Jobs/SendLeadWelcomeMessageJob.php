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
 * The very first WhatsApp message to any Meta Ads lead who isn't already
 * covered by SendVisibilityAuditFirstInviteJob (GMB-tagged leads get that
 * one instead — see LeadObserver, which dispatches exactly one of the two,
 * never both). Meta's native Lead Ads form captures name/phone/email but
 * never opens a real conversation — this both thanks the lead AND asks a
 * specific, easy-to-answer question ("what's a good time to call?") so
 * they're likely to actually reply, which is what opens WhatsApp's 24-hour
 * session window and lets staff (or the after-hours assistant) message
 * freely from then on. See CLAUDE.md's 2026-09-09 decisions log entry.
 *
 * Same wadesk.in POST /api/send-template contract as every other job in
 * this family — no-ops (logs, never throws) until wadesk config and the
 * template name are set. Idempotent on Lead.welcome_message_sent_at — sent
 * at most once per lead, regardless of how many times this is dispatched.
 *
 * Skips a lead staff has already replied to over WhatsApp since it was
 * created (Lead::hasStaffWhatsappReplySince()) — same guard
 * SendVisibilityAuditFirstInviteJob uses, for the same reason: a real
 * human conversation already in progress makes this redundant.
 */
class SendLeadWelcomeMessageJob implements ShouldQueue
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
        $templateName = (string) config('services.wadesk.lead_welcome_template_name');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $lead = Lead::find($this->leadId);

        if ($lead === null || blank($lead->phone) || $lead->welcome_message_sent_at !== null) {
            return;
        }

        if ($lead->hasStaffWhatsappReplySince($lead->created_at)) {
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
                Log::warning('SendLeadWelcomeMessageJob: wadesk.in returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            // Same "still mark it sent so an opted-out lead isn't retried
            // forever" reasoning as SendVisibilityAuditFirstInviteJob --
            // wadesk.in returns 2xx with skipped=true rather than an error.
            $lead->forceFill(['welcome_message_sent_at' => now()])->saveQuietly();

            if ($response->json('skipped') !== true) {
                $lead->notes()->create([
                    'user_id' => null,
                    'body' => '✨ Automated welcome message sent via WhatsApp — asking when\'s a good time to call.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendLeadWelcomeMessageJob: HTTP call to wadesk.in failed', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
