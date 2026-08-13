<?php

namespace App\Http\Requests;

use App\Enums\LeadReassignmentReason;
use App\Enums\UserRole;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadBulkReassignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkReassign', Lead::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'from_user_id' => ['required', Rule::exists('users', 'id')],
            'to_user_id' => [
                'required',
                'different:from_user_id',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)
                    ->whereIn('role', [UserRole::Sales->value, UserRole::Manager->value, UserRole::Admin->value])),
            ],
            'reason' => ['required', Rule::enum(LeadReassignmentReason::class)],
        ];
    }
}
