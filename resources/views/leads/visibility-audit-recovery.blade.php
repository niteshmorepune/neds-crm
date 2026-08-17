<x-app-layout>
    <x-slot name="header">Visibility Audit Funnel</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">
                Meta lead-form → paid, GMB-tagged leads
            </h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="text-xs text-gray-500">Eligible leads</div>
                    <div class="text-xl font-semibold">{{ $funnel['eligible'] }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="text-xs text-gray-500">Invited via WhatsApp</div>
                    <div class="text-xl font-semibold">{{ $funnel['invited'] }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="text-xs text-gray-500">Viewed offer page</div>
                    <div class="text-xl font-semibold">{{ $funnel['landing_viewed'] }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="text-xs text-gray-500">Reached checkout</div>
                    <div class="text-xl font-semibold">{{ $funnel['checkout_viewed'] }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="text-xs text-gray-500">Paid</div>
                    <div class="text-xl font-semibold">{{ $funnel['paid'] }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
            Leads who reached the <a href="{{ route('offers.visibility-audit') }}" class="underline" target="_blank">Visibility Audit offer page</a>
            or its checkout step but haven't paid yet. Reach out — a quick WhatsApp nudge from the lead's own page often closes these out.
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">
                Reached checkout, didn't pay ({{ $stuckAtCheckout->count() }})
            </h3>
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Lead</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Reached checkout</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($stuckAtCheckout as $lead)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $lead->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->phone }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->visibilityAuditFunnelEvents->first()?->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No one currently stuck here.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">
                Saw the offer page, didn't reach checkout ({{ $stuckAtLanding->count() }})
            </h3>
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Lead</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Viewed offer page</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($stuckAtLanding as $lead)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $lead->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->phone }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->visibilityAuditFunnelEvents->first()?->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No one currently stuck here.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
