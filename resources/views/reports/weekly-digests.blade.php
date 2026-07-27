<x-app-layout>
    <x-slot name="header">Weekly Digest History</x-slot>

    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <a href="{{ route('reports.business-overview') }}" class="text-sm font-medium text-indigo-600 hover:underline">← Business Overview</a>
        </div>

        <div class="rounded-md bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-800">
            One row per Monday-morning "Your week ahead" digest. The dashboard only ever shows the latest —
            this page keeps the history so you can see whether cash position, receivables, and at-risk clients
            are trending up or down week over week.
        </div>

        @if ($trend->count() >= 2)
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="mb-3 text-xs font-medium text-gray-500">Last {{ $trend->count() }} weeks</p>
                <div class="h-64">
                    <canvas id="weeklyDigestTrendChart"></canvas>
                </div>
            </div>
        @endif

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500">
                        <th class="px-4 py-2">Week of</th>
                        <th class="px-4 py-2">Summary</th>
                        <th class="px-4 py-2 text-right">Open pipeline</th>
                        <th class="px-4 py-2 text-right">MRR</th>
                        <th class="px-4 py-2 text-right">Cash (3mo)</th>
                        <th class="px-4 py-2 text-right">Receivables</th>
                        <th class="px-4 py-2 text-right">90+ overdue</th>
                        <th class="px-4 py-2 text-right">Flagged clients</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($digests as $digest)
                        <tr class="align-top">
                            <td class="px-4 py-2 font-medium text-gray-900 whitespace-nowrap">{{ $digest->digest_date->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-gray-600 max-w-md">{{ $digest->summary ?? '—' }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">
                                {{ \App\Support\Money::format($digest->pipeline_open_value) }}
                                <span class="text-xs text-gray-400">({{ $digest->pipeline_open_deals_count }})</span>
                            </td>
                            <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">{{ \App\Support\Money::format($digest->mrr_total) }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">{{ \App\Support\Money::format($digest->cash_expected_three_months) }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">{{ \App\Support\Money::format($digest->receivables_total_outstanding) }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">{{ \App\Support\Money::format($digest->receivables_overdue_ninety_plus_days) }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">{{ $digest->client_radar_flagged_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-gray-400">No weekly digests recorded yet — the first one lands the next Monday 09:00 IST.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $digests->links() }}</div>
    </div>

    @if ($trend->count() >= 2)
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    const ctx = document.getElementById('weeklyDigestTrendChart');
                    if (!ctx) return;
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: @json($trend->pluck('digest_date')->map(fn ($d) => $d->format('d M'))),
                            datasets: [
                                {
                                    label: 'MRR (₹)',
                                    data: @json($trend->pluck('mrr_total')->map(fn ($v) => $v / 100)),
                                    borderColor: '#6366f1',
                                    backgroundColor: '#6366f1',
                                    yAxisID: 'y',
                                    tension: 0.3,
                                },
                                {
                                    label: 'Receivables outstanding (₹)',
                                    data: @json($trend->pluck('receivables_total_outstanding')->map(fn ($v) => $v / 100)),
                                    borderColor: '#ef4444',
                                    backgroundColor: '#ef4444',
                                    yAxisID: 'y',
                                    tension: 0.3,
                                },
                                {
                                    label: 'Flagged clients (count)',
                                    data: @json($trend->pluck('client_radar_flagged_count')),
                                    borderColor: '#f59e0b',
                                    backgroundColor: '#f59e0b',
                                    yAxisID: 'y1',
                                    tension: 0.3,
                                },
                            ],
                        },
                        options: {
                            plugins: { legend: { display: true, position: 'bottom' } },
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: '₹' } },
                                y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Clients' } },
                            },
                        },
                    });
                })();
            </script>
        @endpush
    @endif
</x-app-layout>
