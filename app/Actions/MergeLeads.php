<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MergeLeads
{
    /**
     * Merge $duplicate into $primary: reassigns every polymorphic record
     * attached to $duplicate (notes, call logs, meetings, activity history)
     * onto $primary, applies the caller's chosen field values to $primary,
     * leaves a breadcrumb note, then soft-deletes $duplicate — nothing is
     * hard-deleted, matching this app's "relabel/archive, never truly
     * delete" convention (Lead already has SoftDeletes).
     *
     * Tasks are deliberately not touched: Task has no relation to Lead at
     * all in this app (only to Project, which only exists once a deal is
     * won) — there is nothing to reassign.
     *
     * @param  array<string, mixed>  $fields  Final field values to apply to $primary — the caller has already resolved, per field, which of the two leads' values to keep.
     */
    public function handle(Lead $primary, Lead $duplicate, array $fields): Lead
    {
        if ($primary->is($duplicate)) {
            throw new RuntimeException('Cannot merge a lead with itself.');
        }

        return DB::transaction(function () use ($primary, $duplicate, $fields) {
            $primary->update($fields);

            // Not a caller-resolvable field (it's an internal webhook-matching
            // key, never shown in the merge UI) — carry it over automatically,
            // same "backfill only if not already set" rule
            // WhatsappWebhookController::handleUnmatchedNumber() uses for this
            // exact column, so a live conversation never silently strands
            // itself on the soft-deleted duplicate with no way for a future
            // message to find its way back to the surviving lead. Real
            // incident (2026-09-08): a merge left the primary with no
            // conversation_id at all while the duplicate (now soft-deleted,
            // invisible to the webhook's lookup) kept the real one.
            // whatsapp_conversation_id is UNIQUE — the duplicate's own value
            // must be cleared before the primary can take it, or this would
            // throw a unique-constraint violation (soft delete alone doesn't
            // free the slot; the column value is still physically present).
            if ($primary->whatsapp_conversation_id === null && $duplicate->whatsapp_conversation_id !== null) {
                $conversationId = $duplicate->whatsapp_conversation_id;
                $duplicate->update(['whatsapp_conversation_id' => null]);
                $primary->update(['whatsapp_conversation_id' => $conversationId]);
            }

            Note::where('notable_type', Lead::class)->where('notable_id', $duplicate->id)
                ->update(['notable_id' => $primary->id]);

            CallLog::where('callable_type', Lead::class)->where('callable_id', $duplicate->id)
                ->update(['callable_id' => $primary->id]);

            Meeting::where('meetable_type', Lead::class)->where('meetable_id', $duplicate->id)
                ->update(['meetable_id' => $primary->id]);

            // Visibility Audit funnel data — a purchase/touch/funnel-event
            // still pointing at a soft-deleted duplicate becomes invisible
            // to isVisibilityAuditCohort()/the Lead page exactly like an
            // orphaned record (see the 2026-08-27 cohort-detection fix).
            VisibilityAuditPurchase::where('lead_id', $duplicate->id)->update(['lead_id' => $primary->id]);
            VisibilityAuditTouch::where('lead_id', $duplicate->id)->update(['lead_id' => $primary->id]);
            VisibilityAuditFunnelEvent::where('lead_id', $duplicate->id)->update(['lead_id' => $primary->id]);

            // Re-home the duplicate's own history onto the surviving record
            // so its full timeline (creation, edits, status changes) stays
            // visible, not just what happens to it going forward.
            Activity::where('subject_type', Lead::class)->where('subject_id', $duplicate->id)
                ->update(['subject_id' => $primary->id]);

            $primary->notes()->create([
                'user_id' => auth()->id(),
                'body' => "Merged duplicate lead \"{$duplicate->name}\" (#{$duplicate->id}) into this record.",
            ]);

            $duplicate->delete();

            return $primary->fresh();
        });
    }
}
