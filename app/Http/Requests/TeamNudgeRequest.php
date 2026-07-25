<?php

namespace App\Http\Requests;

use App\Enums\NudgeAutoDetectType;
use App\Enums\NudgeRecurrence;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeamNudgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_role' => ['nullable', Rule::in(UserRole::values())],
            'recurrence' => ['required', Rule::in(NudgeRecurrence::values())],
            'auto_detect_type' => ['nullable', Rule::in(NudgeAutoDetectType::values())],
            'due_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('auto_detect_type') && $this->input('recurrence') !== NudgeRecurrence::Weekly->value) {
                $validator->errors()->add('auto_detect_type', 'An auto-detect check is only available on a weekly nudge.');
            }
        });
    }
}
