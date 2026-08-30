<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class BillingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::Manager) ?? false;
    }

    public function rules(): array
    {
        return [
            'default_sac_code' => ['required', 'string', 'max:20'],
        ];
    }
}
