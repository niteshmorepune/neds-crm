<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route/controller authorizes via PartnerPolicy.
        return true;
    }

    public function rules(): array
    {
        return [
            'recurring_invoice_id' => ['required', Rule::exists('recurring_invoices', 'id')],
            'period_start' => ['required', 'date'],
            'billed_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
