<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes via TaskPolicy
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'milestone_id' => ['nullable', Rule::exists('quotation_milestones', 'id'), $this->milestoneBelongsToProjectsDeal()],
            'assignee_id' => ['nullable', Rule::exists('users', 'id'), $this->supportCannotAssignOthers()],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }

    /**
     * A task can only be tagged to a milestone that actually belongs to its
     * own project's deal (via the deal's quotation) — otherwise the "all
     * linked tasks done" suggestion could fire from a client's tasks that
     * have nothing to do with the milestone in question.
     */
    private function milestoneBelongsToProjectsDeal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value) {
                return;
            }

            $projectId = $this->input('project_id');
            $dealId = $projectId ? Project::whereKey($projectId)->value('deal_id') : null;

            if (! $dealId) {
                $fail('A milestone can only be linked to a task on a project with a linked deal.');

                return;
            }

            $belongs = \App\Models\QuotationMilestone::whereKey($value)
                ->whereHas('quotation', fn ($q) => $q->where('deal_id', $dealId))
                ->exists();

            if (! $belongs) {
                $fail('That milestone does not belong to this task\'s project.');
            }
        };
    }

    /**
     * Support staff don't manage employee tasks — they may only leave a task
     * unassigned or assigned to themselves. Reassigning an existing task to
     * someone else is blocked; leaving an existing assignment untouched
     * (e.g. while updating status on a task they merely participate in) is
     * not, since that isn't "managing" anyone else's task.
     */
    private function supportCannotAssignOthers(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $user = $this->user();

            if (! $value || ! $user->hasRole(UserRole::Support)) {
                return;
            }

            $task = $this->route('task');
            $unchanged = $task && (int) $value === $task->assignee_id;

            if ((int) $value !== $user->id && ! $unchanged) {
                $fail('Support staff can only assign tasks to themselves.');
            }
        };
    }
}
