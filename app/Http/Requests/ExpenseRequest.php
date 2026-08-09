<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategory;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in controller
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(ExpenseCategory::values())],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedWithPaise(): array
    {
        $data = $this->validated();
        $data['amount'] = Money::toPaise((float) $data['amount']);

        return $data;
    }
}
