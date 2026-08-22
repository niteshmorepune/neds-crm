<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceLogStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    /** A caller that predates the 'mode' field (or an items-less form submit) means flat. */
    protected function prepareForValidation(): void
    {
        $this->merge(['mode' => $this->input('mode', 'flat')]);
    }

    public function rules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('invoices', 'invoice_number')->withoutTrashed()],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            // 'mode' picks which of the two mutually-exclusive shapes below
            // this submission uses -- a flat lump total (the fast path, the
            // only option before GST line items were mergeable into this
            // same screen) or itemized GST line items (recalculated
            // server-side via GstCalculator, same as the standalone GST
            // Line Items editor).
            'mode' => ['required', 'in:flat,items'],
            'amount' => ['required_if:mode,flat', 'nullable', 'numeric', 'min:0.01'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'is_gst_exempt' => ['nullable', 'boolean'],
            'items' => ['required_if:mode,items', 'nullable', 'array', 'min:1'],
            'items.*.description' => ['required_if:mode,items', 'string', 'max:255'],
            'items.*.sac_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required_if:mode,items', 'numeric', 'gt:0'],
            'items.*.rate' => ['required_if:mode,items', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required_if:mode,items', 'numeric', 'min:0', 'max:28'],
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
