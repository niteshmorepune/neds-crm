<?php

namespace App\Http\Requests;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via LeadPolicy
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            // Entered in rupees; the controller converts to integer paise.
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            // Manual statuses only — "converted" is reached via the convert action.
            'status' => ['required', Rule::in([
                LeadStatus::New->value,
                LeadStatus::Contacted->value,
                LeadStatus::Qualified->value,
                LeadStatus::Lost->value,
            ])],
            'next_follow_up_at' => ['nullable', 'date'],
        ];
    }
}
