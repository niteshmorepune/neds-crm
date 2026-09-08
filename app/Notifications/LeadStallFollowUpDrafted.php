<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A lead has gone quiet and Claude drafted a check-in note on it --
 * staff-only, never sent. See App\Jobs\DraftLeadStallFollowUp. Mirrors
 * DealStallFollowUpDrafted exactly.
 */
class LeadStallFollowUpDrafted extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lead_stall_followup_drafted',
            'lead_id' => $this->lead->id,
            'message' => "Check-in drafted for \"{$this->lead->name}\" — gone quiet, review and send",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
