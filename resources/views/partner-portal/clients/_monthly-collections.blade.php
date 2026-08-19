{{--
    Monthly collection + settlement grid — the Partner Portal replacement for
    the manually-shared Excel sheet (client -> service -> month -> paid/
    pending/upcoming). Deliberately DOES show ₹ amounts, unlike
    _services.blade.php's no-amounts convention above — this section is
    explicitly the Excel replacement, so amounts are the whole point. Don't
    "fix" this back to hiding amounts.
--}}

@if (! empty($settlementGrid))
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h3 class="text-base font-semibold text-gray-900">Monthly Collections</h3>
        <p class="mt-1 text-sm text-gray-500">Trailing 6 months, per service — paid, pending, or upcoming.</p>

        @foreach ($settlementGrid as $service)
            <div class="mt-4">
                <p class="text-sm font-medium text-gray-700">{{ $service['service_name'] }}</p>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2">Month</th>
                                <th class="py-2">Status</th>
                                <th class="py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($service['rows'] as $row)
                                @php
                                    $badge = match ($row['billing_status']) {
                                        'paid', 'collected' => ['bg-emerald-50 text-emerald-700', $row['billing_status'] === 'paid' ? 'Paid' : 'Collected'],
                                        'pending' => ['bg-amber-50 text-amber-700', 'Pending'],
                                        'upcoming' => ['bg-indigo-50 text-indigo-700', 'Upcoming'],
                                        default => ['bg-gray-100 text-gray-600', 'None'],
                                    };
                                @endphp
                                <tr>
                                    <td class="py-2 text-gray-900">{{ $row['label'] }}</td>
                                    <td class="py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge[0] }}">{{ $badge[1] }}</span>
                                    </td>
                                    <td class="py-2 text-right text-gray-700">{{ $row['amount'] !== null ? \App\Support\Money::format($row['amount']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    @php
        $unsettled = collect($settlementGrid)
            ->flatMap(fn ($service) => collect($service['rows'])->pluck('settlement')->filter())
            ->unique('id');
        $settled = $unsettled->filter(fn ($s) => $s->isSettled());
        $pending = $unsettled->reject(fn ($s) => $s->isSettled());
    @endphp

    @if ($unsettled->isNotEmpty())
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Settlement</h3>

            @if ($pending->isNotEmpty())
                @foreach ($pending->groupBy(fn ($s) => $s->owesDirection()) as $direction => $rows)
                    <div class="mt-3 rounded-lg {{ $direction === 'partner_owes_neds' ? 'bg-amber-50' : 'bg-indigo-50' }} px-4 py-3">
                        <p class="text-xs font-medium {{ $direction === 'partner_owes_neds' ? 'text-amber-700' : 'text-indigo-700' }}">
                            {{ $direction === 'partner_owes_neds' ? 'Your share owed to NEDS (pending remittance)' : 'Owed to you by NEDS (pending payment)' }}
                        </p>
                        <p class="mt-1 text-lg font-semibold {{ $direction === 'partner_owes_neds' ? 'text-amber-900' : 'text-indigo-900' }}">
                            {{ \App\Support\Money::format($rows->sum('share_amount')) }}
                        </p>
                    </div>
                @endforeach
            @endif

            @if ($settled->isNotEmpty())
                <p class="mt-3 text-xs text-gray-500">{{ $settled->count() }} month(s) already settled.</p>
            @endif
        </div>
    @endif
@endif
