<x-app-layout>
    <x-slot name="header">Visibility Audit Funnel — All Purchases</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('reports.visibility-audit-funnel', ['from' => $fromInput, 'to' => $toInput]) }}" class="text-sm font-medium text-indigo-600 hover:underline">← Back to funnel dashboard</a>
        </div>

        <p class="text-sm text-gray-500">
            Every Visibility Audit payment in this window, <span class="font-medium text-gray-700">regardless of Meta attribution</span> —
            this is the true total. The funnel dashboard's own "Paid" tile and daily trend are deliberately scoped to just the
            Meta lead-form → GMB cohort, so a purchase from a lead with no Meta history (e.g. a manually shared landing page
            link) won't appear there, but always appears here.
        </p>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="date" name="from" value="{{ $fromInput }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
            <span class="text-xs text-gray-400">to</span>
            <input type="date" name="to" value="{{ $toInput }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
            <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
        </form>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Paid at</th>
                        <th class="px-4 py-3">Payer</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Tier</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Lead</th>
                        <th class="px-4 py-3">Attribution</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($purchases as $purchase)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $purchase->created_at?->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $purchase->payer_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $purchase->payer_phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $purchase->tier?->label() ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ \App\Support\Money::format($purchase->amount_paise) }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                @if ($purchase->lead)
                                    {{ $purchase->lead->name }}
                                @else
                                    <span class="text-gray-300">No lead matched</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($purchase->lead?->meta_leadgen_id)
                                    <span class="inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">Meta lead</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">Other</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                @if ($purchase->lead)
                                    <a href="{{ route('leads.show', $purchase->lead) }}" class="inline-block whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No purchases in this window.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $purchases->links() }}</div>
    </div>
</x-app-layout>
