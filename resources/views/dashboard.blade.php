<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Common to every role --}}
        <livewire:attendance-widget />

        <x-announcement-banner :announcements="$announcements" />

        @if (auth()->user()->ai_daily_digest && auth()->user()->ai_daily_digest_date?->isToday())
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-sm font-medium text-indigo-800">🤖 {{ auth()->user()->ai_daily_digest }}</p>
            </div>
        @endif

        @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager)
                && auth()->user()->ai_weekly_digest && auth()->user()->ai_weekly_digest_date?->isToday())
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-indigo-500">Your week ahead</p>
                <p class="text-sm font-medium text-indigo-800">🤖 {{ auth()->user()->ai_weekly_digest }}</p>
            </div>
        @endif

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

        @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager))
            @php($radarFlaggedCount = app(\App\Services\ClientRadarService::class)->flaggedClients()->count())
            @if ($radarFlaggedCount > 0)
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm font-medium text-orange-800">
                            📡 {{ $radarFlaggedCount }} {{ $radarFlaggedCount === 1 ? 'client needs' : 'clients need' }} attention.
                        </p>
                        <a href="{{ route('client-radar.index') }}" class="shrink-0 text-sm font-medium text-orange-700 hover:underline">View Client Radar →</a>
                    </div>
                </div>
            @endif
        @endif

        @php($nextFestival = \App\Models\Festival::active()->upcomingWithin(7)->orderBy('date')->first())
        @if ($nextFestival)
            <div class="rounded-lg border border-pink-200 bg-pink-50 p-4">
                <p class="text-sm font-medium text-pink-800">
                    @if ($nextFestival->date->isToday())
                        🎉 Happy {{ $nextFestival->name }}, from all of us at NEDS!
                    @else
                        🎉 {{ $nextFestival->name }} is in {{ $nextFestival->daysUntil() }} day{{ $nextFestival->daysUntil() === 1 ? '' : 's' }}!
                    @endif
                </p>
            </div>
        @endif

        <div class="rounded-lg bg-white p-4 shadow-sm flex items-center justify-between">
            <span class="text-sm text-gray-600">End of day? Submit your daily report.</span>
            <a href="{{ route('daily-reports.index') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Daily report</a>
        </div>

        {{-- Role-specific panel --}}
        @include('dashboard.partials.'.$panel, $panelData)
    </div>
</x-app-layout>
