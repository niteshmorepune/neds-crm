<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // token checked by VerifyLeadCaptureToken middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
