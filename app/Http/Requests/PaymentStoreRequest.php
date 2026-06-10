<?php

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via InvoicePolicy
    }

    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date'],
            'mode' => ['required', Rule::enum(PaymentMode::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'], // rupees; controller checks balance
        ];
    }
}
