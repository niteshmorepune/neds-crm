<?php

namespace App\Jobs;

use App\Enums\VisibilityAuditTouchType;
use App\Models\Lead;
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
 * The very first WhatsApp message inviting a Meta Ads lead (tagged the GMB
 * service — see LeadObserver::sendVisibilityAuditInviteIfEligible()) to
 * actually see the Visibility Audit offer. Meta's native Lead Ads form
 * captures name/phone/email but never sends the submitter anywhere — this
 * is the first time they're shown the landing page at all, via the same
 * wadesk.in POST /api/send-template contract as
 * SendVisibilityAuditRecoveryNudgeJob/SendVisibilityAuditPaymentConfirmationJob
 * (Dynamic-URL button, buttonUrlParam = lead id, appended to
 * /offers/visibility-audit/enter so the click is attributed back to this
 * Lead). No-ops (logs, never throws) until wadesk config and the template
 * name are set — ships inert, starts working the moment the template is
 * Meta-approved, no code change needed.
 *
 * Idempotent on Lead.visibility_audit_invited_at — a lead is only ever
 * invited once, regardless of how many times this job is dispatched.
 */
class SendVisibilityAuditFirstInviteJob implements ShouldQueue
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
        $templateName = (string) config('services.wadesk.visibility_audit_first_invite_template_name');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $lead = Lead::find($this->leadId);

        if ($lead === null || blank($lead->phone) || $lead->visibility_audit_invited_at !== null) {
            return;
        }

        // This job is dispatched once, right when the Lead becomes eligible
        // (see LeadObserver), but runs later on the database queue — real
        // time passes before handle() actually executes, long enough for the
        // lead to independently find the offer and pay in between (real
        // incident: lead 242 "Shahdatta Mukadam", 2026-08-19 — paid at
        // 06:37:07, this job fired 4 seconds later at 06:37:11, so a payment-
        // confirmation message was immediately followed by a "come check out
        // our offer" invite). Same fix as SendVisibilityAuditRecoveryNudgeJob:
        // re-check for a completed purchase immediately before sending.
        if ($lead->visibilityAuditPurchases()->exists()) {
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
                    'buttonUrlParam' => (string) $lead->id,
                ]);

            if (! $response->successful()) {
                Log::warning('SendVisibilityAuditFirstInviteJob: wadesk.in returned non-2xx', [
                    'lead_id' => $this->leadId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                VisibilityAuditTouch::logSend($this->leadId, VisibilityAuditTouchType::FirstInvite, false, ['status' => $response->status()]);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('SendVisibilityAuditFirstInviteJob: HTTP call to wadesk.in failed', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);

            VisibilityAuditTouch::logSend($this->leadId, VisibilityAuditTouchType::FirstInvite, false, ['error' => $e->getMessage()]);

            return;
        }

        // wadesk_message_id lets Api\WadeskMessageStatusController find this
        // exact touch back later, if Meta's own async status webhook reports
        // it FAILED after wadesk.in already accepted the send request here —
        // see that controller's docblock for the full "sent ≠ delivered" story.
        VisibilityAuditTouch::logSend($this->leadId, VisibilityAuditTouchType::FirstInvite, true, ['template' => $templateName, 'wadesk_message_id' => $response->json('messageId')]);

        $lead->forceFill(['visibility_audit_invited_at' => now()])->saveQuietly();
    }
}
