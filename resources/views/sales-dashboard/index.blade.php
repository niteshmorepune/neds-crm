<x-app-layout>
    <x-slot name="header">Sales Dashboard</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900">Sales Dashboard</h1>
            <a href="{{ route('deals.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">← Back to Pipeline board</a>
        </div>

        <x-kpi-strip :kpis="$kpis" />

        <x-stage-conversion :stage-conversion="$stageConversion" />

        {{-- Target vs actual --}}
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <p class="mb-3 text-xs font-medium text-gray-500">
                {{ $isManager ? 'Company target vs actual' : 'Your target vs actual' }}
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach (['monthly' => 'This month', 'fy' => 'This financial year'] as $key => $label)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">{{ $label }}</span>
                            @if ($targetProgress[$key])
                                <span class="font-medium text-gray-900">
                                    {{ \App\Support\Money::format($targetProgress[$key]['actual']) }} / {{ \App\Support\Money::format($targetProgress[$key]['target']) }}
                                </span>
                            @else
                                <span class="text-gray-300">No target set</span>
                            @endif
                        </div>
                        @if ($targetProgress[$key])
                            <div class="mt-1 h-2 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full {{ $targetProgress[$key]['pct'] >= 100 ? 'bg-green-500' : 'bg-indigo-500' }}"
                                     style="width: {{ min(100, $targetProgress[$key]['pct'] ?? 0) }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">{{ $targetProgress[$key]['pct'] }}% of target</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Won-value trend --}}
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <p class="mb-3 text-xs font-medium text-gray-500">Won value — last 12 months</p>
            <div class="h-64">
                <canvas id="wonValueTrend"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Service-line breakdown --}}
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <p class="p-4 pb-0 text-xs font-medium text-gray-500">Service-line breakdown</p>
                <table class="mt-2 min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th class="px-4 py-2">Service</th>
                            <th class="px-4 py-2">Open pipeline</th>
                            <th class="px-4 py-2">Won this month</th>
                            <th class="px-4 py-2">Win rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($serviceBreakdown as $row)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $row['service'] }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row['open_pipeline_value']) }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row['won_this_month_value']) }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $row['win_rate'] !== null ? $row['win_rate'].'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-gray-300" colspan="4">No deals yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Needs attention --}}
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="mb-2 text-xs font-medium text-gray-500">Needs attention</p>
                <div class="space-y-4 text-sm">
                    @php
                        $sections = [
                            'stale' => 'Stale — over 10 days in stage',
                            'overdue_followups' => 'Overdue follow-ups',
                            'unowned' => 'Unowned deals',
                            'zero_value' => 'Missing a value (₹0)',
                        ];
                    @endphp
                    @foreach ($sections as $key => $label)
                        <div>
                            <p class="text-xs font-semibold text-gray-600">{{ $label }} ({{ $needsAttention[$key]->count() }})</p>
                            @forelse ($needsAttention[$key] as $deal)
                                <a href="{{ route('deals.show', $deal) }}" class="block truncate py-0.5 text-indigo-600 hover:underline">
                                    {{ $deal->title }} — {{ $deal->customer?->company_name ?? 'Client removed' }}
                                </a>
                            @empty
                                <p class="py-0.5 text-gray-300">Nothing here 🎉</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($isManager)
            {{-- Rep leaderboard + target setting --}}
            <form method="POST" action="{{ route('sales-dashboard.targets.store') }}" class="overflow-hidden overflow-x-auto rounded-lg bg-white p-4 shadow-sm">
                @csrf
                <div class="mb-4 flex flex-wrap items-end gap-4">
                    <div>
                        <x-input-label for="company_monthly_target" value="Company target — this month (₹)" />
                        <x-text-input id="company_monthly_target" name="company_monthly_target" type="number" step="0.01" min="0"
                                      class="mt-1 block w-48"
                                      :value="isset($targetProgress['monthly']['target']) ? \App\Support\Money::toRupees($targetProgress['monthly']['target']) : null" />
                        @if (! isset($targetProgress['monthly']['target']) && ($suggestedTargets['company'] ?? null) !== null)
                            <button type="button"
                                    onclick="document.getElementById('company_monthly_target').value = {{ \App\Support\Money::toRupees($suggestedTargets['company']) }}"
                                    class="mt-1 text-xs text-indigo-600 hover:underline">
                                Suggested: {{ \App\Support\Money::format($suggestedTargets['company']) }} (last 3 months avg. +10%)
                            </button>
                        @endif
                    </div>
                    <div>
                        <x-input-label for="company_fy_target" value="Company target — this FY (₹)" />
                        <x-text-input id="company_fy_target" name="company_fy_target" type="number" step="0.01" min="0"
                                      class="mt-1 block w-48"
                                      :value="isset($targetProgress['fy']['target']) ? \App\Support\Money::toRupees($targetProgress['fy']['target']) : null" />
                    </div>
                    <x-primary-button type="submit">Save targets</x-primary-button>
                </div>

                <p class="mb-2 text-xs font-medium text-gray-500">Rep leaderboard</p>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th class="px-4 py-2">Rep</th>
                            <th class="px-4 py-2">Pipeline</th>
                            <th class="px-4 py-2">Won this month</th>
                            <th class="px-4 py-2">Target this month (₹)</th>
                            <th class="px-4 py-2">% to target</th>
                            <th class="px-4 py-2">Win rate</th>
                            <th class="px-4 py-2">Avg deal size</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($leaderboard as $row)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $row['user']->name }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row['pipeline_value']) }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row['won_this_month_value']) }}</td>
                                <td class="px-4 py-2">
                                    <x-text-input type="number" step="0.01" min="0" class="block w-32"
                                                  id="rep_target_{{ $row['user']->id }}"
                                                  name="rep_targets[{{ $row['user']->id }}]"
                                                  :value="$row['target_value'] ? \App\Support\Money::toRupees($row['target_value']) : null" />
                                    @php $suggested = $row['target_value'] ? null : ($suggestedTargets['reps'][$row['user']->id] ?? null); @endphp
                                    @if ($suggested !== null)
                                        <button type="button"
                                                onclick="document.getElementById('rep_target_{{ $row['user']->id }}').value = {{ \App\Support\Money::toRupees($suggested) }}"
                                                class="mt-0.5 block text-xs text-indigo-600 hover:underline">
                                            Suggested: {{ \App\Support\Money::format($suggested) }}
                                        </button>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-700">{{ $row['target_pct'] !== null ? $row['target_pct'].'%' : '—' }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $row['win_rate'] !== null ? $row['win_rate'].'%' : '—' }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row['avg_deal_size']) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-gray-300" colspan="7">No active sales reps yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </form>
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const ctx = document.getElementById('wonValueTrend');
                if (!ctx) return;
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: @json(collect($trend)->pluck('label')),
                        datasets: [{
                            label: 'Won value (₹)',
                            data: @json(collect($trend)->pluck('value')->map(fn ($v) => $v / 100)),
                            backgroundColor: '#6366f1',
                            borderRadius: 4,
                        }],
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true } },
                    },
                });
            })();
        </script>
    @endpush
</x-app-layout>
