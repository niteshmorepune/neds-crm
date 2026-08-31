<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceNumberSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::Manager) ?? false;
    }

    public function rules(): array
    {
        return [
            'financial_year' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'next_domestic_number' => ['required', 'integer', 'min:1'],
            'next_export_number' => ['required', 'integer', 'min:1'],
            'next_non_gst_number' => ['required', 'integer', 'min:1'],
        ];
    }
}
