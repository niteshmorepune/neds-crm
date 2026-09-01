<?php

namespace App\Actions;

use App\Enums\LeadReassignmentReason;
use App\Models\Lead;
use App\Models\LeadReassignment;
use App\Models\User;
use App\Notifications\LeadReassignedNotification;

/**
 * Single mechanism for moving a lead's ownership from one Sales user to
 * another — used both by the ad-hoc "Reassign" action on the Lead page and
 * the bulk handover UserController::update() triggers when a Sales user is
 * deactivated, so both paths log the same way and notify the same way.
 *
 * The reason isn't a new column on `leads` — it's appended as a visible Note
 * (the same polymorphic timeline already shown on the Lead page), since
 * nothing in the app currently surfaces the `activities` audit trail in the
 * UI, and the point of capturing a reason is for the team to actually see it.
 * owner_id itself still goes through Lead's normal update() save, so
 * LogsActivity's generic 'updated' event still records the raw field change
 * for the audit trail as usual. Also writes a LeadReassignment row -- a
 * structured, aggregable log for the Reassignment Analytics report, since
 * the Note's own reason is free text inside a sentence.
 */
class ReassignLead
{
    public function handle(Lead $lead, User $to, User $by, LeadReassignmentReason $reason): void
    {
        $from = $lead->owner;

        $lead->owner_id = $to->id;
        $lead->save();

        $lead->notes()->create([
            'user_id' => $by->id,
            'body' => sprintf(
                'Reassigned from %s to %s — %s',
                $from?->name ?? 'Unassigned',
                $to->name,
                $reason->label(),
            ),
        ]);

        // Structured log for the Reassignment Analytics report -- the Note
        // above stays the visible timeline entry; this is purely for
        // aggregation, since the Note's reason is free text inside a
        // sentence, not reliably reportable on its own.
        LeadReassignment::create([
            'lead_id' => $lead->id,
            'from_user_id' => $from?->id,
            'to_user_id' => $to->id,
            'reassigned_by' => $by->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $to->notify(new LeadReassignedNotification($lead, $from, $reason));
    }
}
