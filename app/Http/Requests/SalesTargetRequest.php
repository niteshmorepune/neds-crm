<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class SalesTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::Manager) ?? false;
    }

    public function rules(): array
    {
        return [
            'company_monthly_target' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'company_fy_target' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'rep_targets' => ['nullable', 'array'],
            'rep_targets.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
