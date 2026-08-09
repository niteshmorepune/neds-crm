<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <x-announcement-banner :announcements="$announcements" />

        {{-- "Today" panel: every daily-action item and alert renders as one
             compact row here instead of its own full card, so the dashboard's
             actual content (stats, charts, reports below) doesn't get pushed
             below the fold. Attendance is always first and never draws a top
             divider; every other row always draws its own — that way dividers
             stay correct no matter which conditional rows end up rendering,
             including rows that come from separate Livewire islands
             (Attendance, Team Nudges) where a CSS :first-child/divide-y rule
             on the outer shell wouldn't reach across component boundaries. --}}
        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <div class="flex items-center justify-between px-4 py-2.5 sm:px-5">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Today</h2>
                @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager))
                    <a href="{{ route('reports.weekly-digests') }}" class="text-xs font-medium text-indigo-600 hover:underline">Weekly digest history →</a>
                @endif
            </div>

            <livewire:attendance-widget />

            @if (auth()->user()->ai_daily_digest && auth()->user()->ai_daily_digest_date?->isToday())
                <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-indigo-50 text-sm">🤖</span>
                    <p class="min-w-0 flex-1 text-sm text-indigo-900">{{ auth()->user()->ai_daily_digest }}</p>
                </div>
            @endif

            @php($overdueTaskCount = \App\Models\Task::where('assignee_id', auth()->id())
                ->where('status', '!=', \App\Enums\TaskStatus::Done->value)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->count())
            @if ($overdueTaskCount > 0)
                <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-red-50 text-sm">⚠️</span>
                    <p class="min-w-0 flex-1 text-sm font-medium text-red-800">
                        You have {{ $overdueTaskCount }} overdue {{ $overdueTaskCount === 1 ? 'task' : 'tasks' }}.
                    </p>
                    <a href="{{ route('tasks.index', ['mine' => 1]) }}" class="shrink-0 text-sm font-medium text-red-600 hover:underline">View tasks →</a>
                </div>
            @endif

            @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager))
                @php($pendingLeaveCount = \App\Models\LeaveRequest::pending()->count())
                @if ($pendingLeaveCount > 0)
                    <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-amber-50 text-sm">🌴</span>
                        <p class="min-w-0 flex-1 text-sm font-medium text-amber-800">
                            {{ $pendingLeaveCount }} leave {{ $pendingLeaveCount === 1 ? 'request needs' : 'requests need' }} your approval.
                        </p>
                        <a href="{{ route('leave-requests.approvals') }}" class="shrink-0 text-sm font-medium text-amber-700 hover:underline">Review →</a>
                    </div>
                @endif
            @endif

            @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager))
                @php($radarFlaggedCount = app(\App\Services\ClientRadarService::class)->flaggedClients()->count())
                @if ($radarFlaggedCount > 0)
                    <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-orange-50 text-sm">📡</span>
                        <p class="min-w-0 flex-1 text-sm font-medium text-orange-800">
                            {{ $radarFlaggedCount }} {{ $radarFlaggedCount === 1 ? 'client needs' : 'clients need' }} attention.
                        </p>
                        <a href="{{ route('client-radar.index') }}" class="shrink-0 text-sm font-medium text-orange-700 hover:underline">View Client Radar →</a>
                    </div>
                @endif
            @endif

            @php($nextFestival = \App\Models\Festival::active()->upcomingWithin(7)->orderBy('date')->first())
            @if ($nextFestival)
                <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-pink-50 text-sm">🎉</span>
                    <p class="min-w-0 flex-1 text-sm font-medium text-pink-800">
                        @if ($nextFestival->date->isToday())
                            Happy {{ $nextFestival->name }}, from all of us at NEDS!
                        @else
                            {{ $nextFestival->name }} is in {{ $nextFestival->daysUntil() }} day{{ $nextFestival->daysUntil() === 1 ? '' : 's' }}!
                        @endif
                    </p>
                </div>
            @endif

            <livewire:my-team-nudges />

            <livewire:my-follow-up-reminders />

            <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-sm">📝</span>
                <p class="min-w-0 flex-1 text-sm text-gray-600">End of day? Submit your daily report.</p>
                <a href="{{ route('daily-reports.index') }}" class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">Daily report</a>
            </div>
        </div>

        {{-- AI Recommendations — Admin/Manager only. Consolidates every
             "problem → data → recommended action" AI surface that already
             exists into one place: the weekly owner digest (persisted,
             shown here directly) plus a link to the team coaching
             suggestions and productivity gap flags on the Employee
             Performance report (generated on demand there, not re-run here
             — this section reads existing data, it never triggers a new AI
             call itself). Previously the weekly digest sat inside the
             generic "Today" list, mixed in with unrelated items like
             festivals and leave approvals. --}}
        @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager))
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="flex items-center justify-between px-4 py-2.5 sm:px-5">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">🤖 AI Recommendations</h2>
                    <a href="{{ route('reports.employee-performance') }}" class="text-xs font-medium text-indigo-600 hover:underline">Team coaching insights →</a>
                </div>

                @if (auth()->user()->ai_weekly_digest && auth()->user()->ai_weekly_digest_date?->isToday())
                    <div class="border-t border-gray-100 px-4 py-3 sm:px-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Your week ahead</p>
                        <p class="mt-1 text-sm text-indigo-900">{{ auth()->user()->ai_weekly_digest }}</p>
                    </div>
                @else
                    <div class="border-t border-gray-100 px-4 py-3 text-sm text-gray-400 sm:px-5">
                        No new digest yet today — the next one lands Monday morning. Per-employee coaching suggestions and productivity flags are always available on the
                        <a href="{{ route('reports.employee-performance') }}" class="text-indigo-600 hover:underline">Employee Performance report</a>.
                    </div>
                @endif
            </div>
        @endif

        {{-- Role-specific panel --}}
        @include('dashboard.partials.'.$panel, $panelData)
    </div>
</x-app-layout>
