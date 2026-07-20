<?php

namespace App\Http\Requests;

use App\Enums\RecurringFrequency;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(RecurringFrequency::values())],
            'renewal_date' => ['required', 'date'],
            'reminder_days_before' => ['required', 'integer', 'min:1', 'max:90'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedWithPaise(): array
    {
        $data = $this->validated();
        $data['cost'] = Money::toPaise((float) $data['cost']);

        return $data;
    }
}
