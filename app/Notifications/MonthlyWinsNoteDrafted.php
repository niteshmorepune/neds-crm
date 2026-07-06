<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MonthlyWinsNoteDrafted extends Notification
{
    use Queueable;

    public function __construct(public Customer $customer, public string $monthLabel) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monthly_wins_note_drafted',
            'customer_id' => $this->customer->id,
            'message' => "📈 Monthly wins note drafted for {$this->customer->company_name} ({$this->monthLabel}) — review before sending",
            'url' => route('clients.show', $this->customer),
        ];
    }
}
