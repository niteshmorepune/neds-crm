<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoicePaymentPromiseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via InvoicePolicy (recordPayment)
    }

    public function rules(): array
    {
        return [
            'payment_promised_date' => ['nullable', 'date'],
        ];
    }
}
