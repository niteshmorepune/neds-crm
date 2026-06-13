<?php

namespace App\Http\Requests;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallLogStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via CallLogPolicy
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')],
            'lead_id' => ['nullable', Rule::exists('leads', 'id')],
            'direction' => ['required', Rule::enum(CallDirection::class)],
            'outcome' => ['required', Rule::enum(CallOutcome::class)],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'called_at' => ['required', 'date'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'follow_up_at' => ['nullable', 'date'],
        ];
    }
}
