<?php

namespace App\Jobs;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTouchType;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;
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
 * Sends one WhatsApp recovery-nudge template to a Lead stuck at a Visibility
 * Audit funnel stage (see VisibilityAuditFunnelMetrics::pendingLandingNudges()/
 * pendingCheckoutNudges(), dispatched by SendVisibilityAuditRecoveryNudges).
 * Same wadesk.in POST /api/send-template contract as
 * SendVisibilityAuditPaymentConfirmationJob — no-ops (logs, never throws)
 * until wadesk config and the relevant template name are set, so this ships
 * inert and starts working the moment each template is Meta-approved and
 * its env var set, no code change needed.
 *
 * $funnelEventId is the SPECIFIC event this nudge is for (the Lead's latest
 * one at dispatch time) — marking nudged_at on that exact row, not just "the
 * lead", is what lets a lead be nudged again later if they revisit and drop
 * off a second time (a fresh event row, nudged_at still null) without any
 * extra bookkeeping.
 */
class SendVisibilityAuditRecoveryNudgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $leadId,
        public int $funnelEventId,
        public VisibilityAuditFunnelEventType $stage,
    ) {}

    public function handle(): void
    {
        $templateName = (string) config($this->stage === VisibilityAuditFunnelEventType::LandingViewed
            ? 'services.wadesk.visibility_audit_recovery_landing_template_name'
            : 'services.wadesk.visibility_audit_recovery_checkout_template_name');

        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');
        $marketingNumber = (string) config('services.wadesk.marketing_number');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $event = VisibilityAuditFunnelEvent::find($this->funnelEventId);
        if ($event === null || $event->nudged_at !== null) {
            return; // already nudged (or deleted) — never double-send.
        }

        $lead = Lead::find($this->leadId);
        if ($lead === null || blank($lead->phone)) {
            return;
        }

        // The eligibility check ("hasn't paid yet") only ran once, when
        // SendVisibilityAuditRecoveryNudges queried and dispatched this job —
        // on the database queue, real time passes before this handle() runs,
        // long enough for the payment to land in between. Re-check here so a
        // just-paid lead never gets a "still interested?" nudge seconds after
        // their own payment-confirmation message (real incident: lead 2026-08-19,
        // both messages landed the same minute).
        if ($lead->visibilityAuditPurchases()->exists()) {
            return;
        }

        // Same re-check-at-send-time reasoning as the purchase check above:
        // VisibilityAuditFunnelMetrics::pendingLandingNudges()/
        // pendingCheckoutNudges() already filter out a lead staff has replied
        // to since this funnel event, but that's a query-time snapshot — on
        // the database queue, real time passes before this handle() runs, so
        // re-check here too. Real incident, 2026-08-21: a customer already
        // mid-conversation with a human-sent custom proposal still got this
        // nudge.
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
                    // Body has only {{1}}=name — the link now lives on the
                    // template's own CTA button as a Dynamic URL, whose
                    // {{1}} Meta appends to the button's configured base
                    // URL (.../checkout?tier=gbp&lead= or .../enter?lead=),
                    // so only the lead id needs to travel here.
                    'variables' => [$lead->name ?: 'there'],
                    'buttonUrlParam' => (string) $lead->id,
                ]);

            if (! $response->successful()) {
                Log::warning('SendVisibilityAuditRecoveryNudgeJob: wadesk.in returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'stage' => $this->stage->value,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                VisibilityAuditTouch::logSend($this->leadId, $this->touchType(), false, ['status' => $response->status()]);

                return;
            }

            // wadesk.in intentionally skips a MARKETING-category send to a
            // contact who has opted out (e.g. replied "Stop Promotions") --
            // it still returns 2xx, since this isn't a failure, but no
            // message actually went out. Real incident, 2026-08-31: before
            // this check, that response was misread as a successful send.
            // Still mark the event nudged so a known-opted-out lead isn't
            // re-attempted on every future dispatch cycle.
            if ($response->json('skipped') === true) {
                VisibilityAuditTouch::logSend($this->leadId, $this->touchType(), false, ['skipped' => true, 'reason' => $response->json('reason')]);
                $event->update(['nudged_at' => now()]);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditRecoveryNudgeJob: HTTP call to wadesk.in failed', [
                'lead_id' => $this->leadId,
                'stage' => $this->stage->value,
                'error' => $e->getMessage(),
            ]);

            VisibilityAuditTouch::logSend($this->leadId, $this->touchType(), false, ['error' => $e->getMessage()]);

            return;
        }

        VisibilityAuditTouch::logSend($this->leadId, $this->touchType(), true, ['template' => $templateName, 'wadesk_message_id' => $response->json('messageId')]);

        $event->update(['nudged_at' => now()]);
    }

    private function touchType(): VisibilityAuditTouchType
    {
        return $this->stage === VisibilityAuditFunnelEventType::LandingViewed
            ? VisibilityAuditTouchType::RecoveryNudgeLanding
            : VisibilityAuditTouchType::RecoveryNudgeCheckout;
    }
}
