<?php

namespace App\Jobs;

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferPurchaseStatus;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one WhatsApp recovery-nudge template to a Lead stuck at a stage of
 * the unified offer/recommendation funnel (see App\Services\OfferFunnelMetrics,
 * dispatched by App\Console\Commands\SendOfferFunnelRecoveryNudges) — the
 * non-GBP counterpart to SendVisibilityAuditRecoveryNudgeJob, same wadesk.in
 * POST /api/send-template contract, same no-op-until-configured/never-throws
 * behavior. A lead recommended into GbpAudit stays on the existing VA
 * recovery pipeline entirely — never dispatched here.
 *
 * $funnelEventId is the SPECIFIC event this nudge is for (the Lead's latest
 * one at dispatch time) — marking nudged_at on that exact row, not just "the
 * lead", is what lets a lead be nudged again later if they revisit and drop
 * off a second time (a fresh event row, nudged_at still null) without any
 * extra bookkeeping. $stage picks which template: RecommendationCreated (the
 * lead never even opened the recommendation page) is the softer stage;
 * anything else (OfferViewed/OfferCtaClicked — the lead reached their
 * recommended offer's own page but never paid) is the hotter stage.
 */
class SendOfferRecoveryNudgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $leadId,
        public int $funnelEventId,
        public OfferFunnelEventType $stage,
    ) {}

    public function handle(): void
    {
        $templateName = (string) config($this->stage === OfferFunnelEventType::RecommendationCreated
            ? 'services.wadesk.offer_recommendation_recovery_template_name'
            : 'services.wadesk.offer_recovery_template_name');

        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');
        $marketingNumber = (string) config('services.wadesk.marketing_number');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $event = OfferFunnelEvent::find($this->funnelEventId);
        if ($event === null || $event->nudged_at !== null) {
            return; // already nudged (or deleted) — never double-send.
        }

        $lead = Lead::find($this->leadId);
        if ($lead === null || blank($lead->phone) || $lead->recommendation_token === null) {
            return;
        }

        // Real time passes on the database queue between the eligibility
        // query and this handle() actually running — long enough for the
        // lead to have paid, or for staff to have replied, in between. Same
        // dual-layer re-check pattern as SendVisibilityAuditRecoveryNudgeJob.
        if ($lead->offerPurchases()->where('status', OfferPurchaseStatus::Paid)->exists()) {
            return;
        }

        if ($lead->hasStaffWhatsappReplySince($event->created_at)) {
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
                Log::warning('SendOfferRecoveryNudgeJob: wadesk.in returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'stage' => $this->stage->value,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            // wadesk.in intentionally skips a MARKETING-category send to a
            // contact who has opted out — it still returns 2xx, since this
            // isn't a failure, but no message actually went out. Still mark
            // the event nudged so a known-opted-out lead isn't re-attempted
            // on every future dispatch cycle.
            $event->update(['nudged_at' => now()]);

            if ($response->json('skipped') !== true) {
                $lead->notes()->create([
                    'user_id' => null,
                    'body' => '✨ Automated recovery nudge sent via WhatsApp — still hasn\'t completed the offer.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendOfferRecoveryNudgeJob: HTTP call to wadesk.in failed', [
                'lead_id' => $this->leadId,
                'stage' => $this->stage->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
