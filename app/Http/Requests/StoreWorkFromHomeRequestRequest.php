<?php

namespace App\Http\Requests;

use App\Enums\WorkFromHomeRequestStatus;
use App\Enums\WorkFromHomeRequestType;
use App\Models\WorkFromHomeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkFromHomeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated user may request their own WFH
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(WorkFromHomeRequestType::class)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') === WorkFromHomeRequestType::HalfDay->value
                && $this->filled('start_date') && $this->filled('end_date')
                && $this->input('start_date') !== $this->input('end_date')) {
                $validator->errors()->add('end_date', 'A half day WFH request must be for a single date.');
            }

            if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
            }

            $overlaps = WorkFromHomeRequest::where('user_id', $this->user()->id)
                ->whereIn('status', [WorkFromHomeRequestStatus::Pending, WorkFromHomeRequestStatus::Approved])
                ->where('start_date', '<=', $this->input('end_date'))
                ->where('end_date', '>=', $this->input('start_date'))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('start_date', 'You already have a pending or approved WFH request overlapping these dates.');
            }
        });
    }
}
