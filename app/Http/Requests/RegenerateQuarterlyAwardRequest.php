<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateQuarterlyAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via QuarterlyAwardPolicy
    }

    public function rules(): array
    {
        return [
            'financial_year' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'quarter' => ['required', 'integer', 'between:1,4'],
        ];
    }
}
