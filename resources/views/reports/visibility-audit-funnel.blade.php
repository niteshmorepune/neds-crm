<x-app-layout>
    <x-slot name="header">Visibility Audit Funnel Dashboard</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Visibility Audit Funnel Dashboard</h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('reports.visibility-audit-funnel.messages', ['from' => $fromInput, 'to' => $toInput]) }}" class="text-sm font-medium text-indigo-600 hover:underline">Message log →</a>
                <a href="{{ route('leads.visibility-audit-recovery') }}" class="text-sm font-medium text-indigo-600 hover:underline">Recovery worklist →</a>
                <form method="GET" class="flex items-center gap-2">
                    <input type="date" name="from" value="{{ $fromInput }}" class="rounded-md border-gray-300 text-xs shadow-sm" />
                    <span class="text-xs text-gray-400">to</span>
                    <input type="date" name="to" value="{{ $toInput }}" class="rounded-md border-gray-300 text-xs shadow-sm" />
                    <button class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">Filter</button>
                </form>
            </div>
        </div>

        @if ($awaitingServiceTag > 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <strong>{{ $awaitingServiceTag }}</strong> Meta lead(s) in this window have no service tagged yet — they can't enter this funnel until a service is assigned.
                <a href="{{ route('leads.index', ['source' => 'meta_ads']) }}" class="underline">Review Meta leads →</a>
            </div>
        @endif

        @if ($failedTouches > 0)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <strong>{{ $failedTouches }}</strong> AI-WhatsApp send(s) failed in this window — these leads didn't get their message and need a manual follow-up.
                <a href="{{ route('reports.visibility-audit-funnel.messages', ['from' => $fromInput, 'to' => $toInput, 'outcome' => 'failed']) }}" class="underline">Review failed sends →</a>
            </div>
        @endif

        @if ($notYetInvited > 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <strong>{{ $notYetInvited }}</strong> eligible lead(s) in this window haven't been invited yet — likely still queued, but worth checking if it's been more than a few minutes.
                <a href="{{ route('reports.visibility-audit-funnel.leads', ['stage' => 'not_invited', 'from' => $fromInput, 'to' => $toInput]) }}" class="underline">Review →</a>
            </div>
        @else
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <strong>0</strong> eligible leads pending an invite — all caught up.
            </div>
        @endif

        {{-- Funnel stage counts + stage-to-stage conversion --}}
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">
                Meta lead-form → paid, GMB-tagged leads
            </h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                @php
                    $stageHref = fn (string $stage) => route('reports.visibility-audit-funnel.leads', ['stage' => $stage, 'from' => $fromInput, 'to' => $toInput]);
                @endphp
                <a href="{{ $stageHref('eligible') }}" class="rounded-lg bg-white p-4 shadow-sm hover:ring-1 hover:ring-indigo-300">
                    <div class="text-xs text-gray-500">Eligible leads</div>
                    <div class="text-xl font-semibold text-indigo-600">{{ $funnel['eligible'] }}</div>
                </a>
                <a href="{{ $stageHref('invited') }}" class="rounded-lg bg-white p-4 shadow-sm hover:ring-1 hover:ring-indigo-300">
                    <div class="text-xs text-gray-500">Invited via WhatsApp</div>
                    <div class="text-xl font-semibold text-indigo-600">{{ $funnel['invited'] }}</div>
                    <div class="text-xs text-gray-400">{{ $funnel['invited_pct'] !== null ? $funnel['invited_pct'].'% of eligible' : '—' }}</div>
                </a>
                <a href="{{ $stageHref('landing_viewed') }}" class="rounded-lg bg-white p-4 shadow-sm hover:ring-1 hover:ring-indigo-300">
                    <div class="text-xs text-gray-500">Viewed offer page</div>
                    <div class="text-xl font-semibold text-indigo-600">{{ $funnel['landing_viewed'] }}</div>
                    <div class="text-xs text-gray-400">{{ $funnel['landing_pct'] !== null ? $funnel['landing_pct'].'% of invited' : '—' }}</div>
                </a>
                <a href="{{ $stageHref('checkout_viewed') }}" class="rounded-lg bg-white p-4 shadow-sm hover:ring-1 hover:ring-indigo-300">
                    <div class="text-xs text-gray-500">Reached checkout</div>
                    <div class="text-xl font-semibold text-indigo-600">{{ $funnel['checkout_viewed'] }}</div>
                    <div class="text-xs text-gray-400">{{ $funnel['checkout_pct'] !== null ? $funnel['checkout_pct'].'% of viewed' : '—' }}</div>
                </a>
                <a href="{{ $stageHref('paid') }}" class="rounded-lg bg-white p-4 shadow-sm hover:ring-1 hover:ring-indigo-300">
                    <div class="text-xs text-gray-500">Paid</div>
                    <div class="text-xl font-semibold text-indigo-600">{{ $funnel['paid'] }}</div>
                    <div class="text-xs text-gray-400">{{ $funnel['paid_pct'] !== null ? $funnel['paid_pct'].'% of checkout' : '—' }}</div>
                </a>
            </div>
            <p class="mt-1 text-xs text-gray-400">Click a tile to see the leads behind that number.</p>
            <p class="mt-2 text-xs text-gray-500">Overall conversion (paid / eligible): <span class="font-medium text-gray-700">{{ $funnel['overall_pct'] !== null ? $funnel['overall_pct'].'%' : '—' }}</span></p>
        </div>

        {{-- Daily trend --}}
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <p class="mb-3 text-xs font-medium text-gray-500">Daily trend</p>
            <div class="h-64">
                <canvas id="vaFunnelTrend"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- AI vs staff channel activity --}}
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="mb-3 text-xs font-medium text-gray-500">Touches sent, by channel</p>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th class="px-4 py-2">Channel</th>
                            <th class="px-4 py-2">Touches</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">
                                <a href="{{ route('reports.visibility-audit-funnel.messages', ['from' => $fromInput, 'to' => $toInput]) }}" class="text-indigo-600 hover:underline">AI (WhatsApp) →</a>
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ $touchesByChannel['ai_whatsapp'] }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">Staff (call)</td>
                            <td class="px-4 py-2 text-gray-700">{{ $touchesByChannel['staff_call'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Does staff follow-up move the needle --}}
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="mb-3 text-xs font-medium text-gray-500">Paid leads — AI-only vs. staff-assisted</p>
                @if ($conversionByChannel['total'] === 0)
                    <p class="text-sm text-gray-400">No purchases in this window yet.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500">
                                <th class="px-4 py-2">Path to purchase</th>
                                <th class="px-4 py-2">Leads</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">Staff-assisted (called before paying)</td>
                                <td class="px-4 py-2 text-gray-700">{{ $conversionByChannel['staff_assisted'] }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">AI-only</td>
                                <td class="px-4 py-2 text-gray-700">{{ $conversionByChannel['ai_only'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-gray-500">{{ $conversionByChannel['staff_assisted_pct'] }}% of paid leads got a staff call before paying.</p>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const ctx = document.getElementById('vaFunnelTrend');
                if (!ctx) return;
                const trend = @json($trend);
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: trend.map(d => d.label),
                        datasets: [
                            { label: 'Eligible', data: trend.map(d => d.eligible), borderColor: '#9ca3af', backgroundColor: '#9ca3af', tension: 0.2 },
                            { label: 'Invited', data: trend.map(d => d.invited), borderColor: '#6366f1', backgroundColor: '#6366f1', tension: 0.2 },
                            { label: 'Landing viewed', data: trend.map(d => d.landing_viewed), borderColor: '#f59e0b', backgroundColor: '#f59e0b', tension: 0.2 },
                            { label: 'Checkout viewed', data: trend.map(d => d.checkout_viewed), borderColor: '#ef4444', backgroundColor: '#ef4444', tension: 0.2 },
                            { label: 'Paid', data: trend.map(d => d.paid), borderColor: '#10b981', backgroundColor: '#10b981', tension: 0.2 },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    },
                });
            })();
        </script>
    @endpush
</x-app-layout>
