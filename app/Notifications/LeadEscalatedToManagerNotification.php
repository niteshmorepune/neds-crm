<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Speed-to-lead, second stage: the owner already got a reminder
 * (LeadOwnerReminderNotification) and the lead is still untouched later —
 * escalates to every active Admin/Manager. See
 * App\Console\Commands\EscalateUntouchedLeads.
 */
class LeadEscalatedToManagerNotification extends Notification
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
        $owner = $this->lead->owner?->name ?? 'Unassigned';

        return [
            'type' => 'lead_escalated_untouched',
            'lead_id' => $this->lead->id,
            'message' => "{$owner} hasn't acted on lead {$detail} yet — it's been sitting untouched.",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
