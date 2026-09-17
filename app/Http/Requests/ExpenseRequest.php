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

    /**
     * A blank "Paid back on" date input submits as an empty string, not
     * absent — normalize it to null so validatedWithPaise() and the
     * controller can rely on reimbursed_at being either null or a real date.
     */
    protected function prepareForValidation(): void
    {
        if ($this->reimbursed_at === '') {
            $this->merge(['reimbursed_at' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(ExpenseCategory::values())],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reimbursed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedWithPaise(): array
    {
        $data = $this->validated();
        $data['amount'] = Money::toPaise((float) $data['amount']);
        // validated() omits 'reimbursed_at' entirely when the field is absent
        // from the request rather than an empty string — guarantee the key
        // always exists so callers can rely on it being null or a real date.
        $data['reimbursed_at'] ??= null;

        return $data;
    }
}
