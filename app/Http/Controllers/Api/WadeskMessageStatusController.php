<?php

namespace App\Http\Controllers\Api;

use App\Enums\VisibilityAuditTouchChannel;
use App\Http\Controllers\Controller;
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
 * time, which the 3 jobs above now persist as `meta.wadesk_message_id` on
 * their own touch row precisely so it can be found again here. A
 * `message_id` wadesk.in reports that doesn't match any touch (a reply
 * sent outside the VA-funnel flow, or the touch predates this feature) is
 * a harmless no-op — this endpoint only ever downgrades a touch it can
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

        if ($touch === null) {
            return response()->json(['status' => 'no_matching_touch']);
        }

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
}
