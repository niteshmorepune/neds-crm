<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HotLeadNotification extends Notification
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
            'type' => 'hot_lead',
            'lead_id' => $this->lead->id,
            'message' => "Hot lead: {$detail} scored {$this->lead->ai_score}/100".
                ($this->lead->ai_score_reason ? " — {$this->lead->ai_score_reason}" : ''),
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
