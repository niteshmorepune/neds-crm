<x-app-layout>
    <x-slot name="header">Cash Forecast</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <a href="{{ route('reports.business-overview') }}" class="text-sm font-medium text-indigo-600 hover:underline">← Business Overview</a>
        </div>

        <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
            "Recurring expected" and "Receivables due" below are near-certain cash (already contracted or already
            invoiced). The pipeline figure is deliberately kept separate — it's a rough, indicative forecast from
            open deals, not committed cash, and isn't tied to a specific month since deals don't have an expected
            close date.
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Recurring + receivables (next {{ count($forecast['buckets']) }} months)</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($forecast['total_forecast']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Weighted pipeline (indicative)</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-600">{{ \App\Support\Money::format($pipelineWeighted) }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Combined outlook</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($forecast['total_forecast'] + $pipelineWeighted) }}</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-4 shadow-sm">
            <p class="mb-3 text-xs font-medium text-gray-500">By month</p>
            <div class="h-64">
                <canvas id="cashForecastChart"></canvas>
            </div>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500">
                        <th class="px-4 py-2">Month</th>
                        <th class="px-4 py-2">Recurring expected</th>
                        <th class="px-4 py-2">Receivables due</th>
                        <th class="px-4 py-2">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($forecast['buckets'] as $bucket)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $bucket['label'] }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($bucket['recurring_expected']) }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($bucket['receivables_due']) }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ \App\Support\Money::format($bucket['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const ctx = document.getElementById('cashForecastChart');
                if (!ctx) return;
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: @json(collect($forecast['buckets'])->pluck('label')),
                        datasets: [
                            {
                                label: 'Recurring expected (₹)',
                                data: @json(collect($forecast['buckets'])->pluck('recurring_expected')->map(fn ($v) => $v / 100)),
                                backgroundColor: '#6366f1',
                                borderRadius: 4,
                            },
                            {
                                label: 'Receivables due (₹)',
                                data: @json(collect($forecast['buckets'])->pluck('receivables_due')->map(fn ($v) => $v / 100)),
                                backgroundColor: '#22c55e',
                                borderRadius: 4,
                            },
                        ],
                    },
                    options: {
                        plugins: { legend: { display: true, position: 'bottom' } },
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
                    },
                });
            })();
        </script>
    @endpush
</x-app-layout>
