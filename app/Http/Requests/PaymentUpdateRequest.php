<?php

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrects a mistakenly-entered date/mode/reference on an already-recorded
 * payment — deliberately excludes amount/tds_amount, which drive
 * Invoice::balance()/status and already-sent PaymentRecordedNotification;
 * those still require deleting and re-recording the payment.
 */
class PaymentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via InvoicePolicy::recordPayment
    }

    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date'],
            'mode' => ['required', Rule::enum(PaymentMode::class)],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
