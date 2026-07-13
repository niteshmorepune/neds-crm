<x-app-layout>
    <x-slot name="header">Business Overview</x-slot>

    @php $fyStart = (int) $from->year; @endphp

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <select name="fy" class="rounded-md border-gray-300 text-sm shadow-sm">
                    @for ($y = now()->year; $y >= now()->year - 4; $y--)
                        <option value="{{ $y }}" @selected($y === $fyStart)>FY {{ $y }}–{{ ($y + 1) % 100 }}</option>
                    @endfor
                </select>
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.business-overview.export', ['fy' => $fyStart]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        {{-- Top stat cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Total outstanding</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($arAging['total_outstanding']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Total MRR</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-600">{{ \App\Support\Money::format($mrr['total_mrr']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Open pipeline</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($pipeline['open_value']) }}</p>
                <p class="text-xs text-gray-400">{{ $pipeline['open_deals'] }} open deals</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Win rate ({{ $from->format('M Y') }}–{{ $to->format('M Y') }})</p>
                <p class="mt-2 text-2xl font-semibold text-green-600">{{ $pipeline['win_rate_pct'] !== null ? $pipeline['win_rate_pct'].'%' : '—' }}</p>
            </div>
        </div>

        {{-- Partner Performance --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Partner Performance</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2">Partner</th>
                            <th class="py-2 text-right">Referred</th>
                            <th class="py-2 text-right">Active</th>
                            <th class="py-2 text-right">Inactive</th>
                            <th class="py-2 text-right">Won</th>
                            <th class="py-2 text-right">Pipeline</th>
                            <th class="py-2 text-right">Lost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($partners as $p)
                            <tr>
                                <td class="py-2 text-gray-700">{{ $p['partner'] }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $p['customers_referred'] }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $p['customers_active'] }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $p['customers_inactive'] }}</td>
                                <td class="py-2 text-right text-gray-900">{{ $p['deals_won_count'] }} · {{ \App\Support\Money::format($p['deals_won_value']) }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $p['deals_pipeline_count'] }} · {{ \App\Support\Money::format($p['deals_pipeline_value']) }}</td>
                                <td class="py-2 text-right text-gray-600">{{ $p['deals_lost_count'] }} · {{ \App\Support\Money::format($p['deals_lost_value']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-gray-400">No partner-attributed business yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- AR Aging --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">AR Aging</h3>
            @if ($showFinancialDetail)
                <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <ul class="space-y-2 text-sm">
                        @foreach ($arAging['buckets'] as $b)
                            <li class="flex justify-between"><span class="text-gray-700">{{ $b['label'] }}</span><span class="text-gray-900">{{ \App\Support\Money::format($b['total']) }}</span></li>
                        @endforeach
                    </ul>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr><th class="py-2">Customer</th><th class="py-2">Invoice #</th><th class="py-2 text-right">Days overdue</th><th class="py-2 text-right">Balance</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($arAging['invoices'] as $i)
                                    <tr>
                                        <td class="py-2 text-gray-700">{{ $i['customer'] }}</td>
                                        <td class="py-2 text-gray-600">{{ $i['invoice_number'] ?? '—' }}</td>
                                        <td class="py-2 text-right text-gray-600">{{ max(0, $i['days_overdue']) }}</td>
                                        <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($i['balance']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-6 text-center text-gray-400">No outstanding invoices.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <p class="mt-3 text-sm text-gray-400">Full aging detail is limited to Admin/Accounts — see the "Total outstanding" card above.</p>
            @endif
        </div>

        {{-- MRR / Recurring Snapshot --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">MRR / Recurring Snapshot</h3>
            <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ul class="space-y-2 text-sm">
                    @forelse ($mrr['by_service'] as $s)
                        <li class="flex justify-between"><span class="text-gray-700">{{ $s['name'] }}</span><span class="text-gray-900">{{ \App\Support\Money::format($s['monthly_equivalent']) }}</span></li>
                    @empty
                        <li class="text-gray-400">No active recurring contracts.</li>
                    @endforelse
                </ul>
                <div>
                    <p class="text-sm font-medium text-gray-700">Contracts expiring within 30 days</p>
                    @if ($showFinancialDetail)
                        <table class="mt-2 min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr><th class="py-2">Customer</th><th class="py-2">Service</th><th class="py-2">End date</th><th class="py-2 text-right">Monthly</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($mrr['expiring'] as $e)
                                    <tr>
                                        <td class="py-2 text-gray-700">{{ $e['customer'] }}</td>
                                        <td class="py-2 text-gray-600">{{ $e['service'] }}</td>
                                        <td class="py-2 text-gray-600">{{ $e['end_date']->format('d M Y') }}</td>
                                        <td class="py-2 text-right text-gray-900">{{ \App\Support\Money::format($e['monthly_equivalent']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-6 text-center text-gray-400">Nothing expiring soon.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $mrr['expiring_count'] }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Client Concentration --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Client Concentration</h3>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <p class="text-sm text-gray-600">Top 5 clients: <span class="font-semibold text-gray-900">{{ $concentration['top5_pct'] }}%</span> of period revenue</p>
                <p class="text-sm text-gray-600">Top 10 clients: <span class="font-semibold text-gray-900">{{ $concentration['top10_pct'] }}%</span> of period revenue</p>
            </div>
            @if ($showFinancialDetail)
                <ul class="mt-4 space-y-2 text-sm">
                    @forelse ($concentration['clients'] as $c)
                        <li class="flex justify-between"><span class="text-gray-700">{{ $c['name'] }}</span><span class="text-gray-900">{{ \App\Support\Money::format($c['total']) }}</span></li>
                    @empty
                        <li class="text-gray-400">No data.</li>
                    @endforelse
                </ul>
            @endif
        </div>

        {{-- Pipeline & Funnel --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Pipeline & Funnel</h3>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr><th class="py-2">Stage</th><th class="py-2 text-right">Deals</th><th class="py-2 text-right">Value</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($pipeline['pipeline'] as $row)
                        <tr>
                            <td class="py-2 text-gray-700">{{ $row['stage'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $row['deals'] }}</td>
                            <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($row['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <p class="text-sm text-gray-600">Win rate: <span class="font-semibold text-gray-900">{{ $pipeline['win_rate_pct'] !== null ? $pipeline['win_rate_pct'].'%' : '—' }}</span></p>
                <p class="text-sm text-gray-600">Avg deal size: <span class="font-semibold text-gray-900">{{ $pipeline['avg_deal_size'] !== null ? \App\Support\Money::format($pipeline['avg_deal_size']) : '—' }}</span></p>
                <p class="text-sm text-gray-600">Avg sales cycle: <span class="font-semibold text-gray-900">{{ $pipeline['avg_sales_cycle_days'] !== null ? $pipeline['avg_sales_cycle_days'].' days' : '—' }}</span></p>
            </div>
        </div>

        {{-- Related reports --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">See also</h3>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('reports.lead-sources') }}" class="text-indigo-600 hover:underline">Lead Source Performance</a></li>
                <li><a href="{{ route('client-radar.index') }}" class="text-indigo-600 hover:underline">Client Radar</a></li>
            </ul>
        </div>
    </div>
</x-app-layout>
