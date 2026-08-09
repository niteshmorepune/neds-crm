<?php

namespace App\Notifications;

use App\Models\Quotation;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuotationNeedsApproval extends Notification
{
    use Queueable;

    public function __construct(public Quotation $quotation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $customer = $this->quotation->customer?->company_name ?? 'a client';

        return [
            'type' => 'quotation_needs_approval',
            'quotation_id' => $this->quotation->id,
            'message' => "Quotation needs approval: {$customer} — ".Money::format($this->quotation->total),
            'url' => route('quotations.show', $this->quotation),
        ];
    }
}
