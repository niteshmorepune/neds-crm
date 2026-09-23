<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadAssignmentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::Manager) ?? false;
    }

    public function rules(): array
    {
        return [
            'forced_user_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Sales->value)->where('is_active', true),
            ],
        ];
    }
}
