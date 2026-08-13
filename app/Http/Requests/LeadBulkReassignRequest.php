<?php

namespace App\Http\Requests;

use App\Enums\LeadReassignmentReason;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
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
                // A raw `where('role', ...)` here would only check the PRIMARY
                // role column, while the dropdown that offers this value
                // (LeadController::index()'s bulkReassignTargets) is built via
                // withAnyRole() — which also matches an ADDITIONAL role. That
                // mismatch is a real bug that shipped: a user whose primary
                // role is Support but who also holds Sales as an additional
                // role showed up as a selectable target, then silently failed
                // this check every time (2026-08-13 incident). Validate
                // against the exact same eligibility the dropdown used.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $valid = User::where('id', $value)
                        ->where('is_active', true)
                        ->withAnyRole(UserRole::Sales, UserRole::Manager, UserRole::Admin)
                        ->exists();

                    if (! $valid) {
                        $fail('That person is not a valid lead owner (must be an active Sales, Manager, or Admin user).');
                    }
                },
            ],
            'reason' => ['required', Rule::enum(LeadReassignmentReason::class)],
        ];
    }
}
