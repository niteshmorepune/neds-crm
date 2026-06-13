<x-app-layout>
    <x-slot name="header">Daily Reports</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Today — {{ now()->timezone(config('app.timezone'))->format('d M Y') }}</h2>
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

        {{-- My tasks (active) --}}
        @if ($myTasks->isNotEmpty())
        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-900">My Tasks</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Task</th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Update status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($myTasks as $task)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2"><a href="{{ route('tasks.show', $task) }}" class="font-medium text-indigo-600 hover:underline">{{ $task->title }}</a></td>
                            <td class="px-4 py-2 text-gray-500">{{ $task->project?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $task->status->label() }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $task->priority->label() }}</td>
                            <td class="px-4 py-2 {{ $task->isOverdue() ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                {{ $task->due_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('tasks.status', $task) }}">
                                    @csrf @method('PATCH')
                                    <select name="status" class="rounded-md border-gray-300 text-xs shadow-sm" onchange="this.form.submit()">
                                        @foreach ($taskStatuses as $s)
                                            <option value="{{ $s->value }}" @selected($task->status === $s)>{{ $s->label() }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
                        @if ($todayReport?->submitted_at) Submitted {{ $todayReport->submitted_at->timezone(config('app.timezone'))->format('g:i A') }} — saving again updates it. @endif
                    </span>
                    <x-primary-button>{{ $todayReport ? 'Update report' : 'Submit report' }}</x-primary-button>
                </div>
            </form>
        </div>

        {{-- History --}}
        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
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
