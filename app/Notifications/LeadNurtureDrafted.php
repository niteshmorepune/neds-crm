<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadNurtureDrafted extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead, public int $touch) {}

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
            'type' => 'lead_nurture_drafted',
            'lead_id' => $this->lead->id,
            'message' => "Follow-up drafted for {$detail} (touch {$this->touch}/3) — review and send",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
