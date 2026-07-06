<x-app-layout>
    <x-slot name="header">Emptask</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @if ($teamSummary && $teamSummary->isNotEmpty())
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Team workload</h3>
                    <p class="text-xs text-gray-400">Assigned + routine maintenance combined. Click a name to see their full list.</p>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Team member</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-right">To Do</th>
                            <th class="px-4 py-2 text-right">In Progress</th>
                            <th class="px-4 py-2 text-right">Review</th>
                            <th class="px-4 py-2 text-right">Done</th>
                            <th class="px-4 py-2 text-right">Overdue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($teamSummary as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">
                                    <a href="{{ route('tasks.index', ['assignee' => $row['user']->id, 'type' => 'all']) }}" class="font-medium text-indigo-600 hover:underline">
                                        {{ $row['user']->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ $row['total'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $row['todo'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $row['in_progress'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $row['review'] }}</td>
                                <td class="px-4 py-2 text-right text-green-600">{{ $row['done'] }}</td>
                                <td @class(['px-4 py-2 text-right', 'font-medium text-red-600' => $row['overdue'] > 0, 'text-gray-400' => $row['overdue'] === 0])>
                                    {{ $row['overdue'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <label class="flex items-center gap-1 text-sm text-gray-600">
                    <input type="checkbox" name="mine" value="1" @checked(! empty($filters['mine'])) onchange="this.form.submit()" class="rounded border-gray-300 text-indigo-600" />
                    My tasks
                </label>
                <select name="type" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="assigned" @selected(($filters['type'] ?? 'assigned') === 'assigned')>Assigned tasks</option>
                    <option value="routine" @selected(($filters['type'] ?? '') === 'routine')>Routine maintenance</option>
                    <option value="all" @selected(($filters['type'] ?? '') === 'all')>All tasks</option>
                </select>
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select name="priority" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All priorities</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            </form>
            @can('create', \App\Models\Task::class)
                <a href="{{ route('tasks.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">New Task</a>
            @endcan
        </div>

        @if (! empty($filters['assignee']))
            @php($filteredUser = collect($staff)->firstWhere('id', (int) $filters['assignee']))
            <p class="text-xs text-gray-500">
                Filtered to <span class="font-medium text-gray-700">{{ $filteredUser->name ?? 'this team member' }}</span> —
                <a href="{{ route('tasks.index') }}" class="text-indigo-600 hover:underline">clear</a>
            </p>
        @endif

        @if (($filters['type'] ?? 'assigned') !== 'all')
            <p class="text-xs text-gray-400">
                @if (($filters['type'] ?? 'assigned') === 'routine')
                    Showing only 🔄 routine maintenance tasks — created automatically on a schedule for active projects, not assigned by a person.
                @else
                    Hiding 🔄 routine maintenance tasks (auto-created on a schedule for active projects) by default — switch to "Routine maintenance" or "All tasks" above to see them.
                @endif
            </p>
        @endif

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Task</th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Assignee</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tasks as $task)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                @if (is_null($task->created_by))
                                    <span class="mr-1" title="Routine maintenance — created automatically on a schedule">🔄</span>
                                @endif
                                <a href="{{ route('tasks.show', $task) }}" class="font-medium text-indigo-600 hover:underline">{{ $task->title }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $task->project?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $task->priority->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $task->status->label() }}</td>
                            <td class="px-4 py-3 {{ $task->isOverdue() ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                {{ $task->due_date?->format('d M Y') ?? '—' }}{{ $task->isOverdue() ? ' (overdue)' : '' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No tasks found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $tasks->links() }}</div>
    </div>
</x-app-layout>
