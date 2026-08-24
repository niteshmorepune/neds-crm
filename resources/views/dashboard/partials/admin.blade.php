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
