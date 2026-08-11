<?php

namespace App\Notifications;

use App\Models\ClientAdvance;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientAdvanceRecorded extends Notification
{
    use Queueable;

    public function __construct(
        public ClientAdvance $advance,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->advance->customer?->company_name ?? 'Unknown';
        $amount = Money::format($this->advance->amount);

        return [
            'type' => 'client_advance_recorded',
            'client_advance_id' => $this->advance->id,
            'customer_id' => $this->advance->customer_id,
            'message' => "Advance of {$amount} recorded for {$client} — no invoice yet",
            'url' => route('clients.show', $this->advance->customer_id).'#invoices',
        ];
    }
}
