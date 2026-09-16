<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A lead that explicitly asked for expert advice (goal = Not Sure) is STILL
 * untouched well past the owner/telecaller's own nag
 * (LeadWantsExpertAdviceNotification, isReminder=true) -- escalates to every
 * active Admin/Manager. See App\Console\Commands\EscalateNotSureLeads.
 * Distinct from LeadStagnationEscalatedNotification/
 * LeadEscalatedToManagerNotification -- this is a hand-raise a real person
 * made, not passive drift, so the message says so directly rather than a
 * generic "stalled" framing.
 */
class NotSureLeadEscalatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead, public int $hours) {}

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
            'type' => 'notsure_lead_escalated',
            'lead_id' => $this->lead->id,
            'message' => "{$detail} asked for expert advice {$this->hours}+ hours ago — still no follow-up (owner: {$owner}).",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
