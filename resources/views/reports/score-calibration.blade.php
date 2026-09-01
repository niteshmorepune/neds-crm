<x-app-layout>
    <x-slot name="header">Score Calibration</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $from->format('Y-m') }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.score-calibration.export', ['month' => $from->format('Y-m')]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Leads closed this period</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $data['total'] }}</p>
            <p class="text-xs text-gray-400">{{ $from->format('M Y') }} — Converted or Lost, by when they closed</p>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Is the AI score predictive of outcome?</h3>
            <p class="mt-1 text-xs text-gray-400">
                Bands match every lead's own score badge (Cold below 40, Warm 40–69, Hot 70+).
                If Hot leads convert meaningfully more often than Cold ones, the score is doing its job —
                if not, that's a real finding too.
            </p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2">Score band</th>
                            <th class="py-2 text-right">Total</th>
                            <th class="py-2 text-right">Converted</th>
                            <th class="py-2 text-right">Lost</th>
                            <th class="py-2 text-right">Conversion %</th>
                            <th class="py-2 text-right">Avg days (converted)</th>
                            <th class="py-2 text-right">Median days (converted)</th>
                            <th class="py-2 text-right">Avg days (lost)</th>
                            <th class="py-2 text-right">Median days (lost)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($data['buckets'] as $b)
                            <tr>
                                <td class="py-2 font-medium text-gray-800">{{ $b['label'] }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $b['total'] }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $b['converted'] }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $b['lost'] }}</td>
                                <td class="py-2 text-right font-medium text-gray-900">{{ $b['total'] > 0 ? $b['conversion_rate'].'%' : '—' }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $b['avg_days_to_close_converted'] ?? '—' }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $b['median_days_to_close_converted'] ?? '—' }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $b['avg_days_to_close_lost'] ?? '—' }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $b['median_days_to_close_lost'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($data['total'] === 0)
                <p class="mt-4 text-center text-gray-400">No leads closed in this period.</p>
            @endif
        </div>

        <p class="text-xs text-gray-400">
            "No score data" covers a lead that closed before AI scoring was live, or with AI
            turned off at the time — not a data-entry gap.
        </p>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Trend over time</h3>
            <p class="mt-1 text-xs text-gray-400">
                Conversion % per band, recorded monthly (on the 1st, for the month that just ended) —
                separate from the on-demand view above, which always reflects the month picked at the top.
                Is calibration drifting, or holding steady?
            </p>
            @if (count($trend) > 0)
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2">Period</th>
                                <th class="py-2 text-right">Hot</th>
                                <th class="py-2 text-right">Warm</th>
                                <th class="py-2 text-right">Cold</th>
                                <th class="py-2 text-right">No score data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($trend as $row)
                                <tr>
                                    <td class="py-2 font-medium text-gray-800">{{ $row['period_label'] }}</td>
                                    <td class="py-2 text-right text-gray-600">{{ $row['hot'] !== null ? $row['hot'].'%' : '—' }}</td>
                                    <td class="py-2 text-right text-gray-600">{{ $row['warm'] !== null ? $row['warm'].'%' : '—' }}</td>
                                    <td class="py-2 text-right text-gray-600">{{ $row['cold'] !== null ? $row['cold'].'%' : '—' }}</td>
                                    <td class="py-2 text-right text-gray-600">{{ $row['no_score'] !== null ? $row['no_score'].'%' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-4 text-center text-gray-400">No snapshots recorded yet — the first one lands after the current month closes.</p>
            @endif
        </div>
    </div>
</x-app-layout>
