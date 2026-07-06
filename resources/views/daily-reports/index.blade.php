<x-app-layout>
    <x-slot name="header">Daily Reports</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Today — {{ now()->timezone(config('app.display_timezone'))->format('d M Y') }}</h2>
            @if ($canViewTeam)
                <a href="{{ route('daily-reports.team') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Team reports</a>
            @endif
        </div>

        {{-- Auto-filled metrics --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs text-gray-500">Tasks completed</div><div class="text-xl font-semibold">{{ $metrics['tasks_completed'] }}</div></div>
            <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs text-gray-500">Calls made</div><div class="text-xl font-semibold">{{ $metrics['calls_made'] }}</div></div>
            <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs text-gray-500">Leads touched</div><div class="text-xl font-semibold">{{ $metrics['leads_touched'] }}</div></div>
            <div class="rounded-lg bg-white p-4 shadow-sm"><div class="text-xs text-gray-500">Attendance</div><div class="text-xl font-semibold">{{ $metrics['attendance_status'] ? \App\Enums\AttendanceStatus::from($metrics['attendance_status'])->label() : '—' }}</div></div>
        </div>

        {{-- My tasks (active), grouped by project so routine maintenance checks
             don't bury the few tasks that need real attention --}}
        @if ($taskGroups->isNotEmpty())
        <div class="space-y-4">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-base font-semibold text-gray-900">My Tasks</h3>
                @php
                    $totalTaskCount = $taskGroups->sum(fn ($g) => $g['manual']->count() + $g['routine']->count());
                @endphp
                <span class="text-xs text-gray-500">
                    {{ $totalTaskCount }} {{ $totalTaskCount === 1 ? 'task' : 'tasks' }} across {{ $taskGroups->count() }} {{ $taskGroups->count() === 1 ? 'project' : 'projects' }}
                </span>
            </div>

            @foreach ($taskGroups as $group)
                @php
                    $project = $group['project'];
                    $manual = $group['manual'];
                    $routine = $group['routine'];
                    $groupTotal = $manual->count() + $routine->count();
                @endphp
                <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <div class="text-sm">
                            @if ($project)
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $project->name }}</a>
                                @if ($project->customer)
                                    <span class="ml-1 text-xs text-gray-400">· {{ $project->customer->company_name }}</span>
                                @endif
                            @else
                                <span class="font-medium text-gray-900">Other tasks</span>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs text-gray-400">{{ $groupTotal }} {{ $groupTotal === 1 ? 'task' : 'tasks' }}</span>
                    </div>

                    @if ($manual->isNotEmpty())
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($manual as $task)
                                    <x-task-row :task="$task" :task-statuses="$taskStatuses" />
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if ($routine->isNotEmpty())
                        <details @class(['border-gray-100', 'border-t' => $manual->isNotEmpty()])>
                            <summary class="cursor-pointer select-none px-4 py-2 text-xs font-medium text-gray-500 hover:bg-gray-50">
                                🔄 {{ $routine->count() }} routine maintenance {{ $routine->count() === 1 ? 'task' : 'tasks' }} — created automatically on a schedule for this project, click to view
                            </summary>
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($routine as $task)
                                        <x-task-row :task="$task" :task-statuses="$taskStatuses" :muted="true" />
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
        @endif

        {{-- EOD summary --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('daily-reports.store') }}">
                @csrf
                <x-input-label for="summary" value="What I did today *" />
                <textarea id="summary" name="summary" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>{{ old('summary') }}</textarea>
                <x-input-error :messages="$errors->get('summary')" class="mt-1" />
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-xs text-gray-400">
                        @if ($todayReport?->submitted_at) Submitted {{ $todayReport->submitted_at->timezone(config('app.display_timezone'))->format('g:i A') }} — saving again updates it. @endif
                    </span>
                    <x-primary-button>{{ $todayReport ? 'Update report' : 'Submit report' }}</x-primary-button>
                </div>
            </form>
        </div>

        {{-- History --}}
        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3 text-right">Tasks</th><th class="px-4 py-3 text-right">Calls</th><th class="px-4 py-3 text-right">Leads</th><th class="px-4 py-3">Summary</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($history as $report)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-700">{{ $report->date->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-right text-gray-600">{{ $report->tasks_completed }}</td>
                            <td class="px-4 py-2 text-right text-gray-600">{{ $report->calls_made }}</td>
                            <td class="px-4 py-2 text-right text-gray-600">{{ $report->leads_touched }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ \Illuminate\Support\Str::limit($report->summary, 80) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No reports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $history->links() }}</div>
    </div>
</x-app-layout>
