<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\LeadAssignmentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadAssignmentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::Manager) ?? false;
    }

    public function rules(): array
    {
        $rule = $this->route('leadAssignmentRule');

        return [
            'match_type' => ['required', Rule::in(['campaign', 'service', 'va_paid'])],
            'utm_campaign' => ['nullable', 'required_if:match_type,campaign', 'string', 'max:255'],
            'service_id' => ['nullable', 'required_if:match_type,service', Rule::exists('services', 'id')],
            'assigned_user_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Sales->value)->where('is_active', true),
            ],
            'active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active', true)]);
    }

    /**
     * Only one active rule per campaign/service is allowed — two active rules
     * matching the same lead would make routing ambiguous. Checked here
     * (not a DB unique index) since the constraint only applies among active
     * rows and is conditional on match_type.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('active')) {
                return;
            }

            $rule = $this->route('leadAssignmentRule');

            if ($this->input('match_type') === 'campaign' && filled($this->input('utm_campaign'))) {
                $exists = LeadAssignmentRule::active()
                    ->where('utm_campaign', $this->input('utm_campaign'))
                    ->when($rule, fn ($q) => $q->whereKeyNot($rule->id))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('utm_campaign', 'An active rule for this campaign already exists.');
                }
            }

            if ($this->input('match_type') === 'service' && filled($this->input('service_id'))) {
                $exists = LeadAssignmentRule::active()
                    ->where('service_id', $this->input('service_id'))
                    ->when($rule, fn ($q) => $q->whereKeyNot($rule->id))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('service_id', 'An active rule for this service already exists.');
                }
            }

            if ($this->input('match_type') === 'va_paid') {
                $exists = LeadAssignmentRule::active()
                    ->where('va_paid', true)
                    ->when($rule, fn ($q) => $q->whereKeyNot($rule->id))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('match_type', 'An active VA-Paid rule already exists.');
                }
            }
        });
    }

    /**
     * Normalizes match_type into the three nullable/boolean columns actually
     * stored — the controller passes this straight to create()/update().
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'utm_campaign' => $data['match_type'] === 'campaign' ? $data['utm_campaign'] : null,
            'service_id' => $data['match_type'] === 'service' ? $data['service_id'] : null,
            'va_paid' => $data['match_type'] === 'va_paid',
            'assigned_user_id' => $data['assigned_user_id'],
            'active' => $data['active'],
        ];
    }
}
