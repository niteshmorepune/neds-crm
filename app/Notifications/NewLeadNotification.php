<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewLeadNotification extends Notification
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
        $source = $this->lead->source?->label() ?? 'unknown';

        return [
            'type' => 'new_lead',
            'lead_id' => $this->lead->id,
            'message' => "New lead: {$detail} via {$source}",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
