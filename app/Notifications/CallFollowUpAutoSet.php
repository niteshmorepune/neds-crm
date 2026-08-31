<?php

namespace App\Notifications;

use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the rep that DetectCallFollowUpCommitment found a promise in their
 * call notes and set a reminder on their behalf -- they didn't ask for it,
 * so unlike a normal follow-up reminder (silent until due) this fires right
 * away so they can review/adjust it, same transparency principle as
 * HotLeadNotification for an AI-driven lead score.
 */
class CallFollowUpAutoSet extends Notification
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

        $when = $this->call->follow_up_at?->timezone(config('app.display_timezone'))->format('d M, h:i A');

        return [
            'type' => 'call_follow_up_auto_set',
            'call_id' => $this->call->id,
            'callable_name' => $callableName,
            'message' => 'AI spotted a commitment in your call'.
                ($callableName ? " with {$callableName}" : '').
                " and set a follow-up reminder for {$when}. Edit it if that's not right.",
            'next_action' => $this->call->next_action,
            'follow_up_at' => $this->call->follow_up_at?->toIso8601String(),
            'url' => $url,
        ];
    }
}
