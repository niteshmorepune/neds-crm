<x-app-layout>
    <x-slot name="header">Lead Source Performance</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $from->format('Y-m') }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.lead-sources.export', ['month' => $from->format('Y-m')]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Leads captured</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $data['total'] }}</p>
                <p class="text-xs text-gray-400">{{ $from->format('M Y') }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Converted to client</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-600">{{ $data['converted'] }}</p>
                <p class="text-xs text-gray-400">
                    {{ $data['total'] > 0 ? round($data['converted'] / $data['total'] * 100) : 0 }}% conversion rate
                </p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Won value</p>
                <p class="mt-2 text-2xl font-semibold text-green-600">{{ \App\Support\Money::format($data['won_value']) }}</p>
                <p class="text-xs text-gray-400">from deals that closed Won</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">By source</h3>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">Source</th>
                        <th class="py-2 text-right">Leads</th>
                        <th class="py-2 text-right">Converted</th>
                        <th class="py-2 text-right">Conversion %</th>
                        <th class="py-2 text-right">Won value</th>
                        <th class="py-2 text-right">Avg AI score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['by_source'] as $r)
                        <tr>
                            <td class="py-2 text-gray-700">{{ $r['label'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['total'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['converted'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['conversion_rate'] }}%</td>
                            <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($r['won_value']) }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['avg_score'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No leads in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">By campaign</h3>
            <p class="mt-1 text-xs text-gray-400">Only website leads with UTM tracking parameters appear here.</p>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">Campaign</th>
                        <th class="py-2 text-right">Leads</th>
                        <th class="py-2 text-right">Converted</th>
                        <th class="py-2 text-right">Conversion %</th>
                        <th class="py-2 text-right">Won value</th>
                        <th class="py-2 text-right">Avg AI score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['by_campaign'] as $r)
                        <tr>
                            <td class="py-2 text-gray-700">{{ $r['label'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['total'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['converted'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['conversion_rate'] }}%</td>
                            <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($r['won_value']) }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['avg_score'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No UTM-tagged leads in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
