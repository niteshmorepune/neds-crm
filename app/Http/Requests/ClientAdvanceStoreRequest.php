<?php

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientAdvanceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via ClientAdvancePolicy
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'], // rupees
            'received_on' => ['required', 'date'],
            'mode' => ['required', Rule::enum(PaymentMode::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
