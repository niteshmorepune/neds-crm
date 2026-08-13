<?php

namespace App\Http\Requests;

use App\Enums\LeadReassignmentReason;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'additional_roles' => ['nullable', 'array'],
            'additional_roles.*' => [Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'is_active' => ['boolean'],
            'device_user_id' => ['nullable', 'string', 'max:20', Rule::unique('users', 'device_user_id')->ignore($user->id)],
            'reassign_leads_to' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::Sales->value)->where('is_active', true),
                Rule::notIn([$user->id]),
            ],
            'reassign_reason' => ['nullable', Rule::enum(LeadReassignmentReason::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * Deactivating a Sales user who still owns open leads must not be a
     * silent no-op — force the admin to pick who takes those leads over in
     * the same action, rather than leaving them orphaned under an inactive
     * owner (see CLAUDE.md's decisions log for the incident this closes).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->route('user');
            $becomingInactive = $user->is_active && ! $this->boolean('is_active');

            if (! $becomingInactive || $this->filled('reassign_leads_to')) {
                return;
            }

            $openLeadsCount = $user->leads()->whereIn('status', LeadStatus::openValues())->count();

            if ($openLeadsCount > 0) {
                $validator->errors()->add(
                    'reassign_leads_to',
                    "{$user->name} still owns {$openLeadsCount} open lead(s) — choose who should take them over before deactivating.",
                );
            }
        });
    }
}
