<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientAdvanceApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via ClientAdvancePolicy
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'], // rupees; controller caps against remaining/balance
        ];
    }
}
