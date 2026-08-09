<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Requests\TaskStoreRequest;
use App\Models\Project;
use App\Models\QuotationMilestone;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\TaskWorkloadMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request, TaskWorkloadMetrics $workload): View
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        // Defaults to "assigned" (created_by set — a person assigned this
        // directly) whenever the request has no type param at all, so a
        // fresh visit to Emptask isn't dominated by the routine maintenance
        // checks app:dispatch-scheduled-tasks creates daily/weekly/monthly
        // for every active project. request->has() (not filled()) so an
        // explicit ?type= (e.g. "all") is respected rather than re-defaulted.
        $taskType = $request->has('type') ? $request->input('type') : 'assigned';

        $tasks = Task::query()
            ->with(['assignee', 'project'])
            ->unless($isManager, fn ($q) => $q->where(function ($w) use ($user) {
                $w->where('assignee_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('project.assignees', fn ($a) => $a->whereKey($user->id))
                    ->orWhereHas('project', fn ($p) => $p->where('owner_id', $user->id));
            }))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assignee_id', $user->id))
            ->when($request->filled('assignee'), fn ($q) => $q->where('assignee_id', $request->input('assignee')))
            ->when($taskType === 'assigned', fn ($q) => $q->whereNotNull('created_by'))
            ->when($taskType === 'routine', fn ($q) => $q->whereNull('created_by'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->input('priority')))
            // Pseudo-statuses used by dashboard drill-down links, since
            // "pending"/"overdue"/"completed today" aren't single values the
            // plain `status` filter above can express. Mirror the exact
            // definitions DashboardMetrics already uses for these counts.
            ->when($request->boolean('pending'), fn ($q) => $q->where('status', '!=', TaskStatus::Done->value))
            ->when($request->boolean('overdue'), fn ($q) => $q->where('status', '!=', TaskStatus::Done->value)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->startOfDay()))
            ->when($request->boolean('completed_today'), fn ($q) => $q->where('status', TaskStatus::Done->value)
                ->whereDate('updated_at', now()->startOfDay()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('tasks.index', $this->formData() + [
            'tasks' => $tasks,
            'filters' => $request->only(['status', 'priority', 'mine', 'assignee', 'pending', 'overdue', 'completed_today']) + ['type' => $taskType],
            'teamSummary' => $isManager ? $workload->perAssigneeSummary() : null,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        return view('tasks.create', $this->formData() + [
            'task' => new Task([
                'status' => TaskStatus::Todo->value,
                'priority' => TaskPriority::Normal->value,
                'project_id' => $request->integer('project_id') ?: null,
            ]),
        ]);
    }

    public function store(TaskStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $task = Task::create($request->validated() + ['created_by' => $request->user()->id]);

        $this->notifyAssignee($task, null);

        return redirect()->route('tasks.index')->with('status', 'Task created.');
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);

        $task->load(['project', 'assignee', 'creator', 'comments.author', 'attachments.uploader']);

        return view('tasks.show', $this->formData() + [
            'task' => $task,
            'canManage' => $this->user()->can('update', $task),
        ]);
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.edit', $this->formData($task) + ['task' => $task]);
    }

    public function update(TaskStoreRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $previousAssigneeId = $task->assignee_id;
        $task->update($request->validated());

        $this->notifyAssignee($task, $previousAssigneeId);

        return redirect()->route('tasks.show', $task)->with('status', 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(['status' => ['required', Rule::enum(TaskStatus::class)]]);
        $task->update($validated);

        return back()->with('status', 'Task status updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index')->with('status', 'Task deleted.');
    }

    public function storeAttachment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $request->validate(['file' => ['required', 'file', 'max:10240']]); // 10 MB

        $file = $request->file('file');
        $path = $file->store('attachments', 'local');

        $task->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()
            ->with('status', 'Attachment uploaded.')
            ->with('attachment_uploaded', $file->getClientOriginalName())
            ->withFragment('attachments');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Task $task = null): array
    {
        return [
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'staff' => $this->assignableStaff($task),
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'milestonesByProject' => $this->milestonesByProject(),
        ];
    }

    /**
     * Milestones a task's project could be tagged to, keyed by project id —
     * a project's deal's quotation's milestones (only projects with a
     * linked deal have any). Used to filter the Milestone dropdown client-side
     * as the Project dropdown changes, and to validate the link server-side
     * in TaskStoreRequest.
     *
     * @return array<int, array<int, array{id: int, title: string}>>
     */
    private function milestonesByProject(): array
    {
        return Project::whereNotNull('deal_id')->get(['id', 'deal_id'])
            ->mapWithKeys(fn (Project $project) => [
                $project->id => QuotationMilestone::whereHas(
                    'quotation', fn ($q) => $q->where('deal_id', $project->deal_id)
                )->orderBy('sort_order')->get(['id', 'title'])
                    ->map(fn (QuotationMilestone $m) => ['id' => $m->id, 'title' => $m->title])
                    ->all(),
            ])
            ->all();
    }

    /**
     * Support staff don't manage employee tasks, so their assignee dropdown
     * only offers themselves — plus the task's current assignee (if any)
     * so re-saving an existing task they merely participate in doesn't
     * silently unassign it.
     */
    private function assignableStaff(?Task $task): Collection
    {
        $user = $this->user();

        if (! $user->hasRole(UserRole::Support)) {
            return User::orderBy('name')->get(['id', 'name']);
        }

        return User::where('id', $user->id)
            ->when(
                $task?->assignee_id && $task->assignee_id !== $user->id,
                fn ($q) => $q->orWhere('id', $task->assignee_id),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function user(): User
    {
        return auth()->user();
    }

    private function notifyAssignee(Task $task, ?int $previousAssigneeId): void
    {
        if (! $task->assignee_id) {
            return;
        }

        $assigneeChanged = $task->assignee_id !== $previousAssigneeId;
        $notSelf = $task->assignee_id !== $this->user()->id;

        if ($assigneeChanged && $notSelf && $task->assignee) {
            $task->assignee->notify(new TaskAssigned($task));
        }
    }
}
