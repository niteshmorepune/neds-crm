<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Quotation;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewQuotationNotification extends Notification
{
    use Queueable;

    public function __construct(public Quotation $quotation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->quotation->customer?->company_name
            ?? Customer::where('id', $this->quotation->customer_id)->value('company_name')
            ?? 'Unknown';
        $amount = Money::format($this->quotation->total);

        return [
            'type' => 'new_quotation',
            'quotation_id' => $this->quotation->id,
            'message' => "New quotation #{$this->quotation->number} for {$client} — {$amount}",
            'url' => route('quotations.show', $this->quotation->id),
        ];
    }
}
