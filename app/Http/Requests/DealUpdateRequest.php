<?php

namespace App\Http\Requests;

use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Models\Deal;
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
            'lost_reason' => ['required_if:stage,lost', 'nullable', Rule::enum(DealLostReason::class)],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'confidence' => ['nullable', 'integer', 'min:'.Deal::CONFIDENCE_MIN, 'max:'.Deal::CONFIDENCE_MAX],
            'next_follow_up_at' => ['nullable', 'date'],
            'partner_id' => ['nullable', Rule::exists('partners', 'id')],
        ];
    }
}
