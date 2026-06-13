<?php

namespace App\Notifications;

use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CallFollowUpDue extends Notification
{
    use Queueable;

    public function __construct(public CallLog $call) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $callable = $this->call->callable;

        $callableName = match (true) {
            $callable instanceof Customer => $callable->company_name,
            $callable instanceof Lead => $callable->name,
            default => null,
        };

        $url = match (true) {
            $callable instanceof Customer => route('clients.show', $callable->id),
            $callable instanceof Lead => route('leads.show', $callable->id),
            default => route('calls.index'),
        };

        return [
            'type' => 'call_follow_up',
            'call_id' => $this->call->id,
            'callable_name' => $callableName,
            'next_action' => $this->call->next_action,
            'follow_up_at' => $this->call->follow_up_at?->toIso8601String(),
            'url' => $url,
        ];
    }
}
