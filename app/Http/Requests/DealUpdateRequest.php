<?php

namespace App\Http\Requests;

use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via DealPolicy
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', Rule::enum(DealStage::class)],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'next_follow_up_at' => ['nullable', 'date'],
            'partner_id' => ['nullable', Rule::exists('partners', 'id')],
        ];
    }
}
