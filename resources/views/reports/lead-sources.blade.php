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
            <a href="{{ route('leads.index', ['month' => $from->format('Y-m')]) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm text-gray-500">Leads captured</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $data['total'] }}</p>
                <p class="text-xs text-gray-400">{{ $from->format('M Y') }}</p>
            </a>
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
                            <td class="py-2 text-gray-700">
                                <a href="{{ route('leads.index', ['source' => $r['source_value'], 'month' => $from->format('Y-m')]) }}" class="text-indigo-600 hover:underline">{{ $r['label'] }}</a>
                            </td>
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
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">Daily trend — by Sales Rep (owner)</h3>
                <span class="text-xs text-gray-400">{{ $from->format('M Y') }}, Asia/Kolkata calendar days</span>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Day</th>
                            <th class="py-2 px-3 text-right">Total</th>
                            @foreach ($volume['owner_columns'] as $col)
                                <th class="py-2 px-3 text-right">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($volume['days'] as $day)
                            <tr>
                                <td class="py-2 pr-4 text-gray-700">{{ \Illuminate\Support\Carbon::parse($day)->format('D, d M') }}</td>
                                <td class="py-2 px-3 text-right font-medium text-gray-900">{{ $volume['totals'][$day] }}</td>
                                @foreach ($volume['owner_columns'] as $col)
                                    <td class="py-2 px-3 text-right text-gray-600">{{ $volume['by_owner'][$day][$col['key']] ?: '—' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ 2 + count($volume['owner_columns']) }}" class="py-6 text-center text-gray-400">No leads in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($volume['days']) > 0)
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 font-medium text-gray-900">
                                <td class="py-2 pr-4">Total</td>
                                <td class="py-2 px-3 text-right">{{ array_sum($volume['totals']) }}</td>
                                @foreach ($volume['owner_columns'] as $col)
                                    <td class="py-2 px-3 text-right">{{ array_sum(array_column($volume['by_owner'], $col['key'])) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">Daily trend — by Telecaller</h3>
                <span class="text-xs text-gray-400">{{ $from->format('M Y') }}, Asia/Kolkata calendar days</span>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Day</th>
                            <th class="py-2 px-3 text-right">Total</th>
                            @foreach ($volume['telecaller_columns'] as $col)
                                <th class="py-2 px-3 text-right">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($volume['days'] as $day)
                            <tr>
                                <td class="py-2 pr-4 text-gray-700">{{ \Illuminate\Support\Carbon::parse($day)->format('D, d M') }}</td>
                                <td class="py-2 px-3 text-right font-medium text-gray-900">{{ $volume['totals'][$day] }}</td>
                                @foreach ($volume['telecaller_columns'] as $col)
                                    <td class="py-2 px-3 text-right text-gray-600">{{ $volume['by_telecaller'][$day][$col['key']] ?: '—' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ 2 + count($volume['telecaller_columns']) }}" class="py-6 text-center text-gray-400">No leads in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($volume['days']) > 0)
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 font-medium text-gray-900">
                                <td class="py-2 pr-4">Total</td>
                                <td class="py-2 px-3 text-right">{{ array_sum($volume['totals']) }}</td>
                                @foreach ($volume['telecaller_columns'] as $col)
                                    <td class="py-2 px-3 text-right">{{ array_sum(array_column($volume['by_telecaller'], $col['key'])) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
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
