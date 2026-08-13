<?php

namespace App\Http\Requests;

use App\Enums\LeadReassignmentReason;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadReassignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reassign', $this->route('lead')) ?? false;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', Rule::exists('users', 'id')],
            'reason' => ['required', Rule::enum(LeadReassignmentReason::class)],
        ];
    }

    /**
     * Admin/Manager may hand a lead to any active Sales/Manager/Admin user
     * (same owner pool LeadController::formData() already offers on the
     * generic Edit form). A Sales user may only hand off to another active
     * Sales peer, never themselves, never Admin/Manager — peer handoff only,
     * not an escalation path.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $to = User::find($this->input('to_user_id'));

            if ($to === null) {
                return;
            }

            $actor = $this->user();
            $actingAsSalesOnly = $actor->hasRole(UserRole::Sales) && ! $actor->hasRole(UserRole::Admin, UserRole::Manager);

            if (! $actingAsSalesOnly) {
                return;
            }

            if (! $to->is_active || $to->role !== UserRole::Sales || $to->id === $actor->id) {
                $validator->errors()->add('to_user_id', 'You can only hand this lead off to another active Sales team member.');
            }
        });
    }
}
