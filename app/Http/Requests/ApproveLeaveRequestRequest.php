<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveCoverage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mirrors UserUpdateRequest's deactivation-handover rule: a covering
 * teammate is required (not a silent no-op) whenever the leave-taker holds
 * Sales or Telecaller and currently has open leads — otherwise their
 * wadesk.in WhatsApp chats would sit unwatched by anyone but Admin/Manager
 * for the whole leave with no one prompted to notice. Skipped entirely for
 * every other role, or a rep with zero open leads.
 */
class ApproveLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('leaveRequest')) ?? false;
    }

    public function rules(): array
    {
        return [
            'covering_user_id' => ['nullable', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var LeaveRequest $leaveRequest */
            $leaveRequest = $this->route('leaveRequest');
            $coverage = app(LeaveCoverage::class);

            if ($leaveRequest->user === null || ! $coverage->isRequiredFor($leaveRequest->user)) {
                return;
            }

            if (! $this->filled('covering_user_id')) {
                $validator->errors()->add(
                    'covering_user_id',
                    "{$leaveRequest->user->name} has open leads — choose who should cover their WhatsApp chats while they're on leave."
                );

                return;
            }

            $covering = User::find($this->input('covering_user_id'));
            $eligible = $covering !== null
                && $coverage->eligibleCovers($leaveRequest->user)->contains('id', $covering->id);

            if (! $eligible) {
                $validator->errors()->add(
                    'covering_user_id',
                    'Choose an active teammate who shares the same Sales/Telecaller role.'
                );
            }
        });
    }
}
