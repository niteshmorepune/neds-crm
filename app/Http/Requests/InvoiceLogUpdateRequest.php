<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceLogUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    public function rules(): array
    {
        $invoiceId = $this->route('invoice')?->id;

        return [
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('invoices', 'invoice_number')->ignore($invoiceId)],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function attributes(): array
    {
        return [
            'invoice_number' => 'invoice number',
            'customer_id' => 'client',
            'deal_id' => 'deal',
            'project_id' => 'project',
            'issue_date' => 'invoice date',
            'due_date' => 'due date',
        ];
    }
}
