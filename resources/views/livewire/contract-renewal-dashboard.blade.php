<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Contract & Renewal Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Recurring contracts coming up for renewal, and where each one stands in the conversation.</p>
        </div>
        <a href="{{ route('recurring-invoices.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">← Recurring Invoices</a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Renewing in {{ $window }} days</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ collect($counts)->sum() }}</p>
        </div>
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Not started yet</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ $counts['not_started'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">In progress</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ $counts['discussion'] + $counts['proposal_sent'] + $counts['negotiation'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">MRR at stake</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ \App\Support\Money::format($atRiskMrr) }}</p>
        </div>
    </div>

    <div class="rounded-lg bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <div class="flex items-center gap-1 text-sm">
                @foreach ([30, 60, 90] as $days)
                    <button
                        type="button"
                        wire:click="setWindow({{ $days }})"
                        @class([
                            'rounded-md px-3 py-1.5 font-medium',
                            'bg-indigo-600 text-white' => $window === $days,
                            'text-gray-600 hover:bg-gray-100' => $window !== $days,
                        ])
                    >Next {{ $days }} days</button>
                @endforeach
            </div>

            <div class="flex items-center gap-1 text-xs">
                <button type="button" wire:click="setStatusFilter('')" @class(['rounded-full px-2.5 py-1 font-medium', 'bg-gray-900 text-white' => $statusFilter === '', 'bg-gray-100 text-gray-600 hover:bg-gray-200' => $statusFilter !== '']) >All</button>
                @foreach ($statuses as $status)
                    <button type="button" wire:click="setStatusFilter('{{ $status->value }}')" @class(['rounded-full px-2.5 py-1 font-medium', $status->badgeClass() => true, 'ring-2 ring-offset-1 ring-gray-400' => $statusFilter === $status->value]) >{{ $status->label() }} ({{ $counts[$status->value] }})</button>
                @endforeach
            </div>
        </div>

        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <th class="px-5 py-2.5">Client</th>
                    <th class="px-5 py-2.5">Service</th>
                    <th class="px-5 py-2.5">Renews on</th>
                    <th class="px-5 py-2.5">Monthly value</th>
                    <th class="px-5 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($templates as $template)
                    <tr>
                        <td class="px-5 py-3">
                            <a href="{{ route('clients.show', $template->customer) }}" class="font-medium text-indigo-600 hover:underline">{{ $template->customer->company_name }}</a>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $template->service?->name ?? 'Unspecified' }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $template->end_date->format('d M Y') }}
                            @php($daysUntil = today()->diffInDays($template->end_date))
                            <span class="text-xs text-gray-400">({{ $daysUntil === 0 ? 'today' : 'in '.$daysUntil.' days' }})</span>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ \App\Support\Money::format($template->monthlyEquivalentValue()) }}</td>
                        <td class="px-5 py-3">
                            <select wire:change="updateStatus({{ $template->id }}, $event.target.value)" class="rounded-md border-gray-300 text-xs shadow-sm {{ $template->renewal_status->badgeClass() }}">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($template->renewal_status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-gray-400">Nothing renewing in the next {{ $window }} days{{ $statusFilter !== '' ? ' with that status' : '' }}.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
