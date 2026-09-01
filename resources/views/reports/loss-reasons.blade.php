<x-app-layout>
    <x-slot name="header">Loss Reasons</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $from->format('Y-m') }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.loss-reasons.export', ['month' => $from->format('Y-m')]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Deals lost</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $data['total'] }}</p>
                <p class="text-xs text-gray-400">{{ $from->format('M Y') }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">AI suggestion accepted</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-600">{{ $data['ai_suggestion_stats']['accepted_pct'] }}%</p>
                <p class="text-xs text-gray-400">{{ $data['ai_suggestion_stats']['accepted'] }} of {{ $data['total'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Rep overrode suggestion</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $data['ai_suggestion_stats']['overridden'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">No suggestion made</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $data['ai_suggestion_stats']['no_suggestion'] }}</p>
                <p class="text-xs text-gray-400">thin history, or AI declined to guess</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Overall distribution</h3>
            <p class="mt-1 text-xs text-gray-400">What % of losses are due to each reason.</p>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">Reason</th>
                        <th class="py-2 text-right">Count</th>
                        <th class="py-2 text-right">%</th>
                        <th class="py-2 text-right">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['overall'] as $r)
                        <tr>
                            <td class="py-2 text-gray-700">{{ $r['label'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['count'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['pct'] }}%</td>
                            <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($r['value']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-400">No deals lost in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Loss reasons by rep</h3>
            <p class="mt-1 text-xs text-gray-400">A coaching signal, not a ranking — which reasons recur most for each rep.</p>
            <div class="mt-3 space-y-5">
                @forelse ($data['by_rep'] as $group)
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $group['label'] }} <span class="font-normal text-gray-400">({{ $group['total'] }} lost)</span></p>
                        <table class="mt-1 min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($group['by_reason'] as $r)
                                    @if ($r['count'] > 0)
                                        <tr>
                                            <td class="py-1.5 text-gray-600">{{ $r['label'] }}</td>
                                            <td class="py-1.5 text-right text-gray-600">{{ $r['count'] }}</td>
                                            <td class="py-1.5 text-right text-gray-400">{{ $r['pct'] }}%</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="py-6 text-center text-gray-400">No deals lost in this period.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Loss reasons by lead source</h3>
            <div class="mt-3 space-y-5">
                @forelse ($data['by_source'] as $group)
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $group['label'] }} <span class="font-normal text-gray-400">({{ $group['total'] }} lost)</span></p>
                        <table class="mt-1 min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($group['by_reason'] as $r)
                                    @if ($r['count'] > 0)
                                        <tr>
                                            <td class="py-1.5 text-gray-600">{{ $r['label'] }}</td>
                                            <td class="py-1.5 text-right text-gray-600">{{ $r['count'] }}</td>
                                            <td class="py-1.5 text-right text-gray-400">{{ $r['pct'] }}%</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="py-6 text-center text-gray-400">No deals lost in this period.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Loss reasons by score band</h3>
            <p class="mt-1 text-xs text-gray-400">Do high-scored-but-lost deals cluster around a particular reason?</p>
            <div class="mt-3 space-y-5">
                @forelse ($data['by_score_band'] as $group)
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $group['label'] }} <span class="font-normal text-gray-400">({{ $group['total'] }} lost)</span></p>
                        <table class="mt-1 min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($group['by_reason'] as $r)
                                    @if ($r['count'] > 0)
                                        <tr>
                                            <td class="py-1.5 text-gray-600">{{ $r['label'] }}</td>
                                            <td class="py-1.5 text-right text-gray-600">{{ $r['count'] }}</td>
                                            <td class="py-1.5 text-right text-gray-400">{{ $r['pct'] }}%</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="py-6 text-center text-gray-400">No deals lost in this period.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
