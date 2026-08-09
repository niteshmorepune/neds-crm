<?php

namespace App\Notifications;

use App\Models\FollowUpReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FollowUpReminderDue extends Notification
{
    use Queueable;

    public function __construct(public FollowUpReminder $reminder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'follow_up_reminder',
            'reminder_id' => $this->reminder->id,
            'customer_name' => $this->reminder->customer?->company_name,
            'next_action' => $this->reminder->next_action,
            'remind_at' => $this->reminder->remind_at->toIso8601String(),
            'url' => $this->reminder->customer
                ? route('clients.show', $this->reminder->customer_id)
                : route('dashboard'),
        ];
    }
}
