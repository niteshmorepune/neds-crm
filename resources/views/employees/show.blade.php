<x-app-layout>
    <x-slot name="header">Employee 360°</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">{{ $employee->name }}</h1>
                <p class="mt-0.5 text-sm text-gray-500">{{ $employee->allRoles()->map->label()->join(' + ') }} · {{ $employee->email }}</p>
            </div>
            <a href="{{ route('employees.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">← Employee 360°</a>
        </div>

        {{-- Performance summary — reuses ReportMetrics::rankedEmployeePerformance()'s
             already-built score/rank/weakest_metric coaching signal, current month. --}}
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Performance this month</h2>
            @if (! $performance)
                <p class="mt-2 text-sm text-gray-400">No performance data yet.</p>
            @else
                <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Tasks completed</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $performance['tasks_completed'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">On-time %</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $performance['on_time_pct'] !== null ? $performance['on_time_pct'].'%' : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Attendance %</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $performance['attendance_pct'] !== null ? $performance['attendance_pct'].'%' : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Rank in role</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">
                            @if ($performance['rank'])
                                #{{ $performance['rank'] }} of {{ $performance['role_group_size'] }}
                            @else
                                {{ $performance['ranking_note'] ?? '—' }}
                            @endif
                        </p>
                    </div>
                </div>
                @if ($performance['weakest_metric'])
                    <p class="mt-3 text-xs text-amber-700">Focus area: {{ str($performance['weakest_metric'])->replace('_', ' ')->title() }}</p>
                @endif
            @endif
        </div>

        {{-- Still pending — a point-in-time snapshot of everything still open
             for this person (not date-scoped), across every module: tasks,
             tickets, leads, deals, quotations awaiting a client decision, and
             unpaid/overdue invoices. Quotations/invoices are attributed via
             Quotation::ownerId()/Invoice::ownerId() (deal owner, falling back
             to the customer's account owner) since neither model has its own
             owner column. --}}
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Still pending ({{ $pending->count() }})</h2>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($pending as $item)
                    <li class="flex items-center justify-between gap-3 py-1.5">
                        <a href="{{ $item['url'] }}" class="text-gray-800 hover:text-indigo-600 hover:underline">{{ $item['description'] }}</a>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $item['type'] }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-400">Nothing pending — fully caught up.</li>
                @endforelse
            </ul>
        </div>

        {{-- Activity Timeline — everything this person did, chronologically,
             assembled from the activities audit log (already logs who/what/
             when across ~30 core models) + CallLog (calls aren't
             activity-logged). Date range is independent of the "Performance
             this month" panel above. --}}
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">Activity timeline ({{ $timelineEntries->count() }})</h2>
                <form method="GET" class="flex items-center gap-2">
                    <input type="date" name="from" value="{{ $timelineFromInput }}" class="rounded-md border-gray-300 text-xs shadow-sm" />
                    <span class="text-xs text-gray-400">to</span>
                    <input type="date" name="to" value="{{ $timelineToInput }}" class="rounded-md border-gray-300 text-xs shadow-sm" />
                    <button class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">Filter</button>
                </form>
            </div>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($timelineEntries as $entry)
                    <li class="flex items-start justify-between gap-3 py-1.5">
                        <div>
                            @if ($entry['url'])
                                <a href="{{ $entry['url'] }}" class="text-gray-800 hover:text-indigo-600 hover:underline">{{ $entry['description'] }}</a>
                            @else
                                <span class="text-gray-500">{{ $entry['description'] }}</span>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs text-gray-400">{{ $entry['at']->timezone(config('app.display_timezone'))->format('d M, g:i A') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-400">No activity in this range.</li>
                @endforelse
            </ul>
        </div>

        {{-- Workload --}}
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Task workload</h2>
            <div class="mt-3 grid grid-cols-3 gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Total</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $workload['total'] }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Pending</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $workload['pending'] }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Overdue</p>
                    <p @class(['mt-1 text-lg font-semibold', 'text-red-600' => $workload['overdue'] > 0, 'text-gray-900' => $workload['overdue'] === 0])>{{ $workload['overdue'] }}</p>
                </div>
            </div>
        </div>

        {{-- Tickets --}}
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">Tickets assigned</h2>
                <p class="text-xs text-gray-500">{{ $ticketCounts['open'] }} open of {{ $ticketCounts['total'] }} total</p>
            </div>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($tickets as $ticket)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:underline">{{ $ticket->subject }}</a>
                        <span class="text-xs text-gray-500">{{ $ticket->customer->company_name }} · {{ $ticket->status->label() }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-400">No tickets assigned.</li>
                @endforelse
            </ul>
        </div>

        {{-- Attendance --}}
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Recent attendance</h2>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($attendance as $entry)
                    <li class="flex items-center justify-between py-2">
                        <span class="text-gray-600">{{ $entry->date->format('d M Y') }}</span>
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-green-100 text-green-700' => $entry->status->value === 'present',
                            'bg-amber-100 text-amber-700' => $entry->status->value === 'half_day',
                            'bg-blue-100 text-blue-700' => $entry->status->value === 'leave',
                            'bg-red-100 text-red-700' => $entry->status->value === 'absent',
                        ])>{{ $entry->status->label() }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-400">No attendance records yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Manager notes — reuses the same RecordNotes component as the
             Users edit page, now also open to Manager (not just Admin). --}}
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900 mb-1">Manager Notes</h2>
            <p class="text-xs text-gray-400 mb-4">Feedback, areas of improvement, follow-up actions — visible only to Admin/Manager, never to the employee.</p>
            <livewire:record-notes :record="$employee" :can-manage="true" />
        </div>
    </div>
</x-app-layout>
