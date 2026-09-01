<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A lead the owner was already emailed about (SendStagnationAlerts) is still
 * untouched well past that first threshold -- escalates to every active
 * Admin/Manager, same recipient pattern as LeadEscalatedToManagerNotification
 * but a distinct class/type since this is a different failure mode: a lead
 * that went cold after real engagement, not a brand-new one nobody ever
 * touched. Deliberately not deduped (re-fires daily for as long as the lead
 * stays stagnant), matching SendStagnationAlerts' own owner-facing email,
 * which already re-sends every day rather than tracking a "already alerted"
 * flag -- a persistent nag until someone acts.
 */
class LeadStagnationEscalatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead, public int $days) {}

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
            'type' => 'lead_stagnation_escalated',
            'lead_id' => $this->lead->id,
            'message' => "Lead stalled — no activity in {$this->days}+ days: {$detail} (owner: {$owner}).",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
