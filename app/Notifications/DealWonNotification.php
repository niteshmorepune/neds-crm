<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Deal;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DealWonNotification extends Notification
{
    use Queueable;

    public function __construct(public Deal $deal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->deal->customer?->company_name
            ?? Customer::where('id', $this->deal->customer_id)->value('company_name')
            ?? 'Unknown';
        $value = Money::format($this->deal->value);

        return [
            'type' => 'deal_won',
            'deal_id' => $this->deal->id,
            'message' => "Deal won: {$this->deal->title} — {$client} ({$value})",
            'url' => route('deals.show', $this->deal->id),
        ];
    }
}
