<?php

namespace App\Http\Requests;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated user may request their own leave
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
            }

            $overlaps = LeaveRequest::where('user_id', $this->user()->id)
                ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved])
                ->where('start_date', '<=', $this->input('end_date'))
                ->where('end_date', '>=', $this->input('start_date'))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('start_date', 'You already have a pending or approved leave request overlapping these dates.');
            }
        });
    }
}
