<?php

namespace App\Actions;

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConvertLead
{
    /**
     * Convert a lead into a Customer (+ primary Contact) and a Deal, in one
     * transaction. Marks the lead converted and links both records back so the
     * history is preserved. Returns the new Deal.
     */
    public function handle(Lead $lead): Deal
    {
        if ($lead->status === LeadStatus::Converted) {
            throw new RuntimeException('Lead has already been converted.');
        }

        return DB::transaction(function () use ($lead) {
            $customer = Customer::create([
                'company_name' => $lead->company ?: $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'owner_id' => $lead->owner_id,
                'status' => CustomerStatus::Active->value,
            ]);

            Contact::create([
                'customer_id' => $customer->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'is_primary' => true,
            ]);

            $deal = Deal::create([
                'title' => $lead->company ?: $lead->name,
                'customer_id' => $customer->id,
                'service_id' => $lead->service_id,
                'value' => $lead->estimated_value ?? 0,
                'stage' => DealStage::New->value,
                'owner_id' => $lead->owner_id,
                'next_follow_up_at' => $lead->next_follow_up_at,
                'lead_id' => $lead->id,
            ]);

            $lead->update([
                'status' => LeadStatus::Converted->value,
                'converted_customer_id' => $customer->id,
                'converted_deal_id' => $deal->id,
                'converted_at' => now(),
            ]);

            // Preserve a breadcrumb on the new client's timeline.
            $customer->notes()->create([
                'user_id' => auth()->id(),
                'body' => "Converted from lead: {$lead->name} (source: {$lead->source->label()}).",
            ]);

            return $deal;
        });
    }
}
