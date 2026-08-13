<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Speed-to-lead: a nudge to the owner of a brand-new lead nobody has
 * touched yet (no note, call, or edit) a short while after it was
 * assigned — see App\Console\Commands\EscalateUntouchedLeads.
 */
class LeadOwnerReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $detail = $this->lead->company
            ? "{$this->lead->name} ({$this->lead->company})"
            : $this->lead->name;

        return [
            'type' => 'lead_owner_reminder',
            'lead_id' => $this->lead->id,
            'message' => "Still no action on lead: {$detail} — call or message them while it's fresh.",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
