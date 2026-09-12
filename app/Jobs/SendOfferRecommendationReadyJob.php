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
 * The first WhatsApp message pointing a Meta Ads lead at their own
 * personalized recommendation page — dispatched from
 * App\Actions\GenerateLeadRecommendation the moment a lead's goal+budget
 * answers resolve to one of the 3 non-GBP entry offers (LeadGenerationAudit/
 * WebsiteGrowthAudit/GrowthStrategy). A lead recommended into GbpAudit
 * instead gets the existing, unchanged SendVisibilityAuditFirstInviteJob —
 * see that action's docblock and the 2026-09-12 "unified funnel" decisions
 * log entry for why. Same wadesk.in POST /api/send-template contract as
 * every other job in this family — no-ops (logs, never throws) until wadesk
 * config and the template name are set.
 *
 * Idempotent on Lead.recommendation_notified_at — sent at most once per
 * lead, regardless of how many times this is dispatched (e.g. the lead's
 * recommendation later changes to a different one of the 3 offers).
 *
 * Skips a lead staff has already replied to over WhatsApp since it was
 * created (Lead::hasStaffWhatsappReplySince()) — same guard every other
 * first-touch job in this family uses: a real human conversation already in
 * progress makes an automated intro redundant.
 *
 * Unlike the Visibility Audit family, this does not log to VisibilityAuditTouch
 * (that table is VA-specific) — a plain internal Note on success instead,
 * same pattern as SendLeadWelcomeMessageJob/SendLeadCheckInJob.
 */
class SendOfferRecommendationReadyJob implements ShouldQueue
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
        $templateName = (string) config('services.wadesk.offer_recommendation_template_name');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $lead = Lead::find($this->leadId);

        if ($lead === null || blank($lead->phone) || $lead->recommendation_notified_at !== null || $lead->recommendation_token === null) {
            return;
        }

        // Real time passes on the database queue between dispatch and this
        // handle() actually running — long enough for staff to have started
        // a real conversation in between. Same guard every other first-touch
        // job in this family uses.
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
                    'variables' => [$lead->name ?: 'there'],
                    'buttonUrlParam' => $lead->recommendation_token,
                ]);

            if (! $response->successful()) {
                Log::warning('SendOfferRecommendationReadyJob: wadesk.in returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            // wadesk.in intentionally skips a MARKETING-category send to a
            // contact who has opted out — it still returns 2xx, since this
            // isn't a failure, but no message actually went out. Still mark
            // the lead notified so a known-opted-out lead isn't re-attempted
            // on a future dispatch. Same pattern as every other job in this
            // family.
            $lead->forceFill(['recommendation_notified_at' => now()])->saveQuietly();

            if ($response->json('skipped') !== true) {
                $lead->notes()->create([
                    'user_id' => null,
                    'body' => '✨ Automated recommendation-ready message sent via WhatsApp, pointing them at their personalized offer page.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendOfferRecommendationReadyJob: HTTP call to wadesk.in failed', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
