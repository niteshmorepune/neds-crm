<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Common to every role --}}
        <livewire:attendance-widget />

        @php($overdueTaskCount = \App\Models\Task::where('assignee_id', auth()->id())
            ->where('status', '!=', \App\Enums\TaskStatus::Done->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count())
        @if ($overdueTaskCount > 0)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-medium text-red-800">
                        ⚠️ You have {{ $overdueTaskCount }} overdue {{ $overdueTaskCount === 1 ? 'task' : 'tasks' }}. Please complete them as soon as possible.
                    </p>
                    <a href="{{ route('tasks.index', ['mine' => 1]) }}" class="shrink-0 text-sm font-medium text-red-600 hover:underline">View tasks →</a>
                </div>
            </div>
        @endif

        @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager))
            @php($pendingLeaveCount = \App\Models\LeaveRequest::pending()->count())
            @if ($pendingLeaveCount > 0)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm font-medium text-amber-800">
                            🌴 {{ $pendingLeaveCount }} leave {{ $pendingLeaveCount === 1 ? 'request needs' : 'requests need' }} your approval.
                        </p>
                        <a href="{{ route('leave-requests.approvals') }}" class="shrink-0 text-sm font-medium text-amber-700 hover:underline">Review →</a>
                    </div>
                </div>
            @endif
        @endif

        <div class="rounded-lg bg-white p-4 shadow-sm flex items-center justify-between">
            <span class="text-sm text-gray-600">End of day? Submit your daily report.</span>
            <a href="{{ route('daily-reports.index') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Daily report</a>
        </div>

        {{-- Role-specific panel --}}
        @include('dashboard.partials.'.$panel, $panelData)
    </div>
</x-app-layout>
