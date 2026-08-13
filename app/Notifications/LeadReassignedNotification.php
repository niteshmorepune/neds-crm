<?php

namespace App\Notifications;

use App\Enums\LeadReassignmentReason;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Same shape/pattern as NewLeadNotification, deliberately a separate class
 * rather than reusing it — "New lead: X" would be a misleading message for a
 * lead that isn't new, and doesn't carry the handover reason.
 */
class LeadReassignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public ?User $from,
        public LeadReassignmentReason $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $detail = $this->lead->company
            ? "{$this->lead->name} ({$this->lead->company})"
            : $this->lead->name;

        $fromPart = $this->from ? " from {$this->from->name}" : '';

        return [
            'type' => 'lead_reassigned',
            'lead_id' => $this->lead->id,
            'message' => "Lead reassigned to you{$fromPart}: {$detail} — {$this->reason->label()}",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
