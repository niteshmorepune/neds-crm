<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Requests\TaskStoreRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        $tasks = Task::query()
            ->with(['assignee', 'project'])
            ->unless($isManager, fn ($q) => $q->where(function ($w) use ($user) {
                $w->where('assignee_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('project.assignees', fn ($a) => $a->whereKey($user->id));
            }))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assignee_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->input('priority')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('tasks.index', $this->formData() + [
            'tasks' => $tasks,
            'filters' => $request->only(['status', 'priority', 'mine']),
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

        return redirect()->route('tasks.show', $task)->with('status', 'Task created.');
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

        return view('tasks.edit', $this->formData() + ['task' => $task]);
    }

    public function update(TaskStoreRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

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

        return back()->with('status', 'Attachment uploaded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
        ];
    }

    private function user(): User
    {
        return auth()->user();
    }
}
