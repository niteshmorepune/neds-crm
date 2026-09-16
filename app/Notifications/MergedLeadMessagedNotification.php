<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A trashed Lead's own WhatsApp conversation just received another
 * message, but it wasn't an incidental delete — it was merged into a Lead
 * that's still active (App\Actions\MergeLeads). Restoring it unconditionally
 * (the original 2026-09-16 fix) silently re-creates exactly the
 * fragmentation the merge consolidated — confirmed to have already
 * happened twice in production (#421->#420, #422->#423), one of which
 * staff had to manually re-delete on noticing it shouldn't have come back.
 *
 * There's no code-level way to know whether the merged-away Lead's own
 * number is the one actually still in use (it was, in the #421 case) or
 * genuinely dormant — that's a human judgment call. This notification
 * carries everything needed to make it: both Leads, both numbers, when
 * the merge happened, and which number the new message arrived on. See
 * WhatsappWebhookController::recordMessageOnMergedAwayLead() for what
 * happens to the message itself (attached to the primary Lead's notes,
 * clearly labeled — never lost, never silently restored).
 */
class MergedLeadMessagedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Lead $trashedLead,
        public Lead $primary,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'merged_lead_messaged',
            'trashed_lead_id' => $this->trashedLead->id,
            'primary_lead_id' => $this->primary->id,
            'message' => "{$this->trashedLead->name} ({$this->trashedLead->phone}) just messaged again on WhatsApp. This lead was merged into \"{$this->primary->name}\" (#{$this->primary->id}, {$this->primary->phone}) on {$this->trashedLead->deleted_at}. Not auto-restored — the message is on {$this->primary->name}'s timeline for now. Decide: does {$this->trashedLead->name}'s number need to become a separate lead again, or should this conversation just stay attached to {$this->primary->name}?",
            'url' => route('leads.show', $this->primary->id),
        ];
    }
}
