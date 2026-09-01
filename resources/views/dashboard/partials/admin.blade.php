@php
    $card = function (string $key, string $label, array $c, string $href) {
        return ['key' => $key, 'label' => $label, 'value' => $c['value'], 'change' => $c['change'], 'href' => $href];
    };
    $cards = collect([
        $card('clients_total', 'Total Clients', $stats['clients_total'], route('clients.index', ['status' => 'all'])),
        $card('clients_active', 'Active Clients', $stats['clients_active'], route('clients.index', ['status' => \App\Enums\CustomerStatus::Active->value])),
        $card('clients_inactive', 'Inactive Clients', $stats['clients_inactive'], route('clients.index', ['status' => \App\Enums\CustomerStatus::Inactive->value])),
        $card('leads_total', 'Total Leads', $stats['leads_total'], route('leads.index')),
        $card('tasks_overview', 'Tasks Overview', $stats['tasks_total'], route('tasks.index', ['type' => 'all'])),
    ])->filter(fn ($c) => in_array($c['key'], $visibleWidgets))->values();
@endphp

{{-- Row 1: stat cards --}}
@if ($cards->isNotEmpty())
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($cards as $c)
            <a href="{{ $c['href'] }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm text-gray-500">{{ $c['label'] }}</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($c['value']) }}</p>
                <p @class([
                    'mt-1 text-xs font-medium',
                    'text-green-600' => $c['change'] >= 0,
                    'text-red-600' => $c['change'] < 0,
                ])>
                    {{ $c['change'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($c['change']), 1) }}% from last month
                </p>
            </a>
        @endforeach
    </div>
@endif

{{-- Row 1.5: Pending Approvals --}}
@if (in_array('pending_approvals', $visibleWidgets))
    <a href="{{ route('approval-center.index') }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md sm:max-w-xs">
        <p class="text-sm text-gray-500">Pending Approvals</p>
        <p @class([
            'mt-2 text-3xl font-semibold',
            'text-amber-600' => $pendingApprovals > 0,
            'text-gray-900' => $pendingApprovals === 0,
        ])>{{ number_format($pendingApprovals) }}</p>
        <p class="mt-3 text-sm text-indigo-600">
            {{ $pendingApprovals > 0 ? 'Review pending approvals →' : 'Nothing waiting on you →' }}
        </p>
    </a>
@endif

{{-- Row 1.7: Ongoing Projects + Upcoming Payments & Renewals --}}
@if (in_array('ongoing_projects', $visibleWidgets) || in_array('upcoming_payments', $visibleWidgets))
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @if (in_array('ongoing_projects', $visibleWidgets))
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Ongoing Projects</h3>
                    <a href="{{ route('project-health.index') }}" class="text-sm text-indigo-600 hover:underline">View all {{ $ongoingProjects->count() }} →</a>
                </div>
                @if ($ongoingProjects->isNotEmpty())
                    <ul class="mt-4 divide-y divide-gray-100">
                        @foreach ($ongoingProjects->take(6) as $row)
                            @php
                                $project = $row['project'];
                            @endphp
                            <li class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0">
                                <span class="mt-0.5 text-sm leading-none">
                                    @switch($row['status'])
                                        @case('red') 🔴 @break
                                        @case('orange') 🟠 @break
                                        @case('yellow') 🟡 @break
                                        @default 🟢
                                    @endswitch
                                </span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('projects.show', $project) }}" class="block truncate text-sm font-medium text-gray-900 hover:underline">{{ $project->name }}</a>
                                    <p class="truncate text-xs text-gray-500">{{ $project->customer->company_name }} · {{ $row['completion'] !== null ? $row['completion'].'% done' : 'No tasks yet' }}</p>
                                    <p class="truncate text-xs text-gray-400">
                                        @if ($row['current_task'])
                                            Current: {{ $row['current_task']->title }} — {{ $row['current_task']->assignee?->name ?? 'Unassigned' }}
                                        @else
                                            No open tasks
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-6 text-sm text-gray-400">No active or on-hold projects.</p>
                @endif
            </div>
        @endif

        @if (in_array('upcoming_payments', $visibleWidgets))
            @php
                $paymentBuckets = [
                    ['overdue', 'Overdue', 'text-red-600'],
                    ['due_7', 'Next 7 days', 'text-amber-600'],
                    ['due_30', 'Next 30 days', 'text-gray-700'],
                    ['due_60', 'Next 60 days', 'text-gray-700'],
                ];
                $allUpcoming = collect($upcomingPayments)->flatten(1);
            @endphp
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Upcoming Payments &amp; Renewals</h3>
                    <a href="{{ route('reports.receivables') }}" class="text-sm text-indigo-600 hover:underline">Receivables report →</a>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($paymentBuckets as [$key, $label, $fg])
                        <div>
                            <p class="{{ $fg }} text-xl font-semibold">{{ $upcomingPayments[$key]->count() }}</p>
                            <p class="text-xs text-gray-500">{{ $label }}</p>
                            <p class="text-xs text-gray-400">{{ \App\Support\Money::format((int) $upcomingPayments[$key]->sum('amount')) }}</p>
                        </div>
                    @endforeach
                </div>
                @if ($allUpcoming->isNotEmpty())
                    <ul class="mt-4 divide-y divide-gray-100">
                        @foreach ($allUpcoming->take(6) as $item)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm first:pt-0 last:pb-0">
                                <a href="{{ $item['href'] }}" class="min-w-0 flex-1 truncate hover:underline">
                                    <span class="text-gray-900">{{ $item['customer']?->company_name ?? 'Client removed' }}</span>
                                    <span class="text-gray-400">· {{ $item['label'] }}</span>
                                </a>
                                <span class="shrink-0 text-xs text-gray-500">{{ $item['date']->format('d M') }}</span>
                                <span class="shrink-0 text-xs font-medium text-gray-700">{{ \App\Support\Money::format($item['amount']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-6 text-sm text-gray-400">Nothing due in the next 60 days.</p>
                @endif
            </div>
        @endif
    </div>
@endif

{{-- Row 2: Services Overview donut + Task Summary --}}
@if (in_array('services_overview', $visibleWidgets) || in_array('task_summary', $visibleWidgets))
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        @if (in_array('services_overview', $visibleWidgets))
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Services Overview</h3>
                    <a href="{{ route('projects.index', ['group' => 'service']) }}" class="text-sm text-indigo-600 hover:underline">View projects →</a>
                </div>
                @if ($services['total'] > 0)
                    <div class="mt-4 flex flex-col items-center gap-6 sm:flex-row">
                        <div class="relative h-48 w-48 shrink-0">
                            <canvas id="servicesDonut"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-2xl font-semibold text-gray-900">{{ number_format($services['total']) }}</span>
                                <span class="text-xs text-gray-500">Total Projects</span>
                            </div>
                        </div>
                        <ul class="flex-1 space-y-2 text-sm">
                            @foreach ($services['segments'] as $i => $seg)
                                <li class="flex items-center justify-between gap-3">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ ['#6366f1','#22c55e','#f59e0b','#ef4444','#06b6d4','#a855f7','#ec4899'][$i % 7] }}"></span>
                                        <span class="text-gray-700">{{ $seg['name'] }}</span>
                                    </span>
                                    <span class="text-gray-500">{{ $seg['count'] }} · {{ $seg['percent'] }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="mt-6 text-sm text-gray-400">No projects yet.</p>
                @endif
            </div>
        @endif

        @if (in_array('task_summary', $visibleWidgets))
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Task Summary</h3>
                @php
                    $segments = [
                        ['Assigned', $tasks['assigned'], 'text-indigo-600', route('tasks.index', ['type' => 'all'])],
                        ['Pending', $tasks['pending'], 'text-amber-600', route('tasks.index', ['type' => 'all', 'pending' => 1])],
                        ['Overdue', $tasks['overdue'], 'text-red-600', route('tasks.index', ['type' => 'all', 'overdue' => 1])],
                        ['Completed', $tasks['completed'], 'text-green-600', route('tasks.index', ['type' => 'all', 'status' => \App\Enums\TaskStatus::Done->value])],
                    ];
                    $barTotal = max(1, $tasks['pending'] + $tasks['overdue'] + $tasks['completed']);
                @endphp
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($segments as [$label, $value, $fg, $href])
                        <a href="{{ $href }}" class="block hover:opacity-75">
                            <p class="text-2xl font-semibold {{ $fg }}">{{ number_format($value) }}</p>
                            <p class="text-xs text-gray-500">{{ $label }}</p>
                        </a>
                    @endforeach
                </div>
                <div class="mt-5 flex h-2.5 overflow-hidden rounded-full bg-gray-100">
                    @foreach ([['bg-amber-500', $tasks['pending']], ['bg-red-500', $tasks['overdue']], ['bg-green-500', $tasks['completed']]] as [$bg, $value])
                        <div class="{{ $bg }}" style="width: {{ round(($value / $barTotal) * 100, 1) }}%"></div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif

{{-- Row 3: link panels --}}
@if (in_array('daily_reports_links', $visibleWidgets) || in_array('project_dashboard_links', $visibleWidgets) || in_array('reports_links', $visibleWidgets))
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @if (in_array('daily_reports_links', $visibleWidgets))
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Daily Reports</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('daily-reports.team') }}" class="text-indigo-600 hover:underline">Employee Reports</a></li>
                    <li><a href="{{ route('daily-reports.index') }}" class="text-indigo-600 hover:underline">My Reports</a></li>
                </ul>
            </div>
        @endif
        @if (in_array('project_dashboard_links', $visibleWidgets))
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Project Dashboard</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('projects.index', ['group' => 'client']) }}" class="text-indigo-600 hover:underline">Client-wise</a></li>
                    <li><a href="{{ route('projects.index', ['group' => 'owner']) }}" class="text-indigo-600 hover:underline">Employee-wise</a></li>
                    <li><a href="{{ route('projects.index', ['group' => 'service']) }}" class="text-indigo-600 hover:underline">Service-wise</a></li>
                </ul>
            </div>
        @endif
        @if (in_array('reports_links', $visibleWidgets))
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Reports</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('reports.business-overview') }}" class="text-indigo-600 hover:underline">Business Overview</a></li>
                    <li><a href="{{ route('reports.cash-forecast') }}" class="text-indigo-600 hover:underline">Cash Forecast</a></li>
                    <li><a href="{{ route('reports.revenue') }}" class="text-indigo-600 hover:underline">Revenue Report</a></li>
                    <li><a href="{{ route('reports.employee-performance') }}" class="text-indigo-600 hover:underline">Employee Performance Report</a></li>
                    <li><a href="{{ route('reports.lead-sources') }}" class="text-indigo-600 hover:underline">Lead Source Performance</a></li>
                    <li><a href="{{ route('reports.reassignment-analytics') }}" class="text-indigo-600 hover:underline">Reassignment Analytics</a></li>
                    <li><a href="{{ route('reports.ai-usage') }}" class="text-indigo-600 hover:underline">AI Usage Report</a></li>
                    <li><a href="{{ route('reports.ask') }}" class="text-indigo-600 hover:underline">Ask the CRM</a></li>
                    <li><a href="{{ route('reports.receivables') }}" class="text-indigo-600 hover:underline">Outstanding Receivables</a></li>
                    @if (auth()->user()->isAdmin())
                        <li><a href="{{ route('audit-log') }}" class="text-indigo-600 hover:underline">Audit Log</a></li>
                    @endif
                </ul>
            </div>
        @endif
    </div>
@endif

@if ($services['total'] > 0)
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const ctx = document.getElementById('servicesDonut');
                if (!ctx) return;
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: @json(collect($services['segments'])->pluck('name')),
                        datasets: [{
                            data: @json(collect($services['segments'])->pluck('count')),
                            backgroundColor: ['#6366f1','#22c55e','#f59e0b','#ef4444','#06b6d4','#a855f7','#ec4899'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        cutout: '70%',
                        plugins: { legend: { display: false } },
                        responsive: true,
                        maintainAspectRatio: false,
                    },
                });
            })();
        </script>
    @endpush
@endif
