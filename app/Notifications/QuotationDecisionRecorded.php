<?php

namespace App\Notifications;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Quotation;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired at the staff owner when a client accepts or rejects a quotation
 * directly in the portal — this is the loop-closer for the in-portal
 * Accept/Reject action; previously a client's decision only ever reached
 * staff by phone/email.
 */
class QuotationDecisionRecorded extends Notification
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
        $decision = $this->quotation->status === QuotationStatus::Accepted ? 'accepted' : 'rejected';

        return [
            'type' => 'quotation_decision_recorded',
            'quotation_id' => $this->quotation->id,
            'message' => "{$client} {$decision} quotation #{$this->quotation->number} — {$amount}",
            'url' => route('quotations.show', $this->quotation->id),
        ];
    }
}
