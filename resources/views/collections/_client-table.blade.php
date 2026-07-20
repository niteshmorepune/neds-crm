@php($showPartnerColumn ??= false)

<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="py-2">Client</th>
                @if ($showPartnerColumn)
                    <th class="py-2">Partner</th>
                @endif
                <th class="py-2 text-right">Recurring not paid</th>
                <th class="py-2 text-right">Other unpaid</th>
                <th class="py-2 text-right">Partial — pending</th>
                <th class="py-2 text-right">Oldest overdue</th>
                <th class="py-2">Payment promised</th>
                <th class="py-2">Projects</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $row)
                <tr>
                    <td class="py-2 text-gray-900">
                        <a href="{{ route('clients.show', $row['customer']) }}" class="font-medium text-indigo-600 hover:underline">
                            {{ $row['customer']->company_name }}
                        </a>
                    </td>
                    @if ($showPartnerColumn)
                        <td class="py-2 text-gray-600">{{ $row['partner'] ?? 'Direct' }}</td>
                    @endif
                    <td class="py-2 text-right text-gray-700">
                        @if ($row['recurring_overdue_count'] > 0)
                            {{ $row['recurring_overdue_count'] }} · {{ \App\Support\Money::format($row['recurring_overdue_amount']) }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="py-2 text-right text-gray-700">
                        @if ($row['other_unpaid_count'] > 0)
                            {{ $row['other_unpaid_count'] }} · {{ \App\Support\Money::format($row['other_unpaid_amount']) }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="py-2 text-right text-gray-700">
                        @if ($row['partial_count'] > 0)
                            {{ $row['partial_count'] }} · {{ \App\Support\Money::format($row['partial_amount']) }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="py-2 text-right text-gray-600">
                        @if ($row['oldest_overdue_days'] !== null)
                            {{ $row['oldest_overdue_days'] }} days
                            <span class="text-gray-400">({{ rtrim(rtrim(number_format($row['oldest_overdue_months'], 1), '0'), '.') }} mo)</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="py-2">
                        @if ($row['payment_promised_date'])
                            <span @class([
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-red-100 text-red-800' => $row['promise_broken'],
                                'bg-blue-100 text-blue-800' => ! $row['promise_broken'],
                            ])>
                                {{ $row['payment_promised_date']->format('d M Y') }}
                                @if ($row['promise_broken']) · broken @endif
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="py-2">
                        @forelse ($row['projects'] as $project)
                            <div class="mb-1 last:mb-0">
                                <a href="{{ route('projects.show', $project['id']) }}" class="text-gray-700 hover:underline">{{ $project['name'] }}</a>
                                <span class="text-gray-400">
                                    · {{ $project['completion_percentage'] !== null ? $project['completion_percentage'].'% done' : 'no tasks yet' }}
                                </span>
                                @if ($project['milestone'])
                                    <span class="text-gray-500">
                                        · next: {{ $project['milestone']['title'] }} ({{ rtrim(rtrim((string) $project['milestone']['percentage'], '0'), '.') }}%, {{ $project['milestone']['status']->label() }})
                                    </span>
                                    @if ($project['milestone']['ready_to_invoice'])
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Ready to invoice</span>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <span class="text-gray-300">—</span>
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showPartnerColumn ? 8 : 7 }}" class="py-6 text-center text-gray-400">
                        Nothing needs attention here right now.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
