<?php

namespace App\Http\Controllers\Api;

use App\Enums\VisibilityAuditTouchChannel;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\VisibilityAuditTouch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * wadesk.in → CRM bridge closing a real gap found 2026-08-23: every
 * SendVisibilityAuditFirstInviteJob/RecoveryNudgeJob/PaymentConfirmationJob
 * logs a VisibilityAuditTouch as `success = true` the moment wadesk.in's
 * POST /api/send-template synchronously ACCEPTS the request — but that only
 * means wadesk.in queued the send with Meta, not that WhatsApp actually
 * delivered it. Meta reports the real outcome asynchronously, sometimes
 * minutes later, via its own status webhook — which wadesk.in already
 * receives and stores on its own Message row, but (before this) never
 * forwarded anywhere. A message can fail for a real reason (e.g. Meta's
 * "healthy ecosystem engagement" throttle, error 131049) while the CRM's
 * funnel dashboard/message log keeps showing it as a clean "Sent ✓"
 * forever, silently overstating how many invites actually reached anyone.
 *
 * wadesk.in's `handleStatusUpdate()` now calls this whenever a message it
 * sent transitions to FAILED, passing back the wadesk `Message.id` — the
 * exact same id `/api/send-template`'s response already returned at send
 * time, which the 3 VA jobs persist as `meta.wadesk_message_id` on their
 * own touch row, and (2026-09-10) SendLeadWelcomeMessageJob/
 * SendLeadCheckInJob persist as `leads.welcome_message_wadesk_id`/
 * `checkin_wadesk_id` directly on the Lead — precisely so either can be
 * found again here. Real incident that surfaced the Lead-side gap: a
 * lead_welcome/lead_checkin send hit this same pacing throttle, but since
 * neither job persisted its wadesk message id anywhere, the CRM had no way
 * to ever learn the send had actually failed — the lead's timeline kept
 * the "✨ sent" note forever, indistinguishable from a real delivery.
 *
 * A `message_id` wadesk.in reports that doesn't match any touch or lead (a
 * reply sent outside either flow, or the send predates this feature) is a
 * harmless no-op — this endpoint only ever downgrades a record it can
 * positively identify, never guesses.
 */
class WadeskMessageStatusController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message_id' => ['required', 'string'],
            'error_code' => ['nullable', 'integer'],
            'error_message' => ['nullable', 'string'],
        ]);

        $touch = VisibilityAuditTouch::query()
            ->where('channel', VisibilityAuditTouchChannel::AiWhatsapp)
            ->whereJsonContains('meta->wadesk_message_id', $data['message_id'])
            ->first();

        if ($touch !== null) {
            $touch->update([
                'success' => false,
                'meta' => [
                    ...($touch->meta ?? []),
                    'error' => $data['error_message'] ?? 'Delivery failed (reported by wadesk.in)',
                    'error_code' => $data['error_code'] ?? null,
                ],
            ]);

            return response()->json(['status' => 'touch_updated', 'touch_id' => $touch->id]);
        }

        $lead = Lead::query()
            ->where('welcome_message_wadesk_id', $data['message_id'])
            ->orWhere('checkin_wadesk_id', $data['message_id'])
            ->first();

        if ($lead !== null) {
            $reason = $data['error_message'] ?? 'Delivery failed (reported by wadesk.in)';

            if ($lead->welcome_message_wadesk_id === $data['message_id']) {
                // Reopens welcome eligibility (nulling the guard
                // SendLeadWelcomeMessageJob/SendLeadWelcomeFollowUps read) —
                // the message never actually reached the lead, so nothing
                // should behave as if their 24h session window ever opened.
                $lead->forceFill([
                    'welcome_message_sent_at' => null,
                    'welcome_message_wadesk_id' => null,
                ])->saveQuietly();
                $lead->notes()->create([
                    'user_id' => null,
                    'body' => "❌ Welcome WhatsApp message failed to deliver: {$reason}",
                ]);
            } else {
                // Clears the 24h cooldown so staff can immediately retry the
                // "Send WhatsApp check-in" button instead of it silently
                // believing a check-in already went out.
                $lead->forceFill([
                    'last_checkin_sent_at' => null,
                    'checkin_wadesk_id' => null,
                ])->saveQuietly();
                $lead->notes()->create([
                    'user_id' => null,
                    'body' => "❌ Re-engagement check-in failed to deliver: {$reason}",
                ]);
            }

            return response()->json(['status' => 'lead_updated', 'lead_id' => $lead->id]);
        }

        return response()->json(['status' => 'no_matching_touch']);
    }
}
