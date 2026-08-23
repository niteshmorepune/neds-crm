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

        @php
            $myGapCount = $myAwaitingServiceTag->count() + $myStuckAtCheckout->count() + $myStuckAtLanding->count() + $myUnansweredReplies->count();
        @endphp
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">
                Your gaps ({{ $myGapCount }})
            </h3>
            <p class="mb-2 text-xs text-gray-500">Just your own leads — untagged, stuck with no follow-up yet, or waiting on a reply from you.</p>
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Lead</th>
                            <th class="px-4 py-3">What's needed</th>
                            <th class="px-4 py-3">Since</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($myAwaitingServiceTag as $lead)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $lead->name }}</td>
                                <td class="px-4 py-3 text-amber-700">Tag a service (GMB) — no automation runs until this is set</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->created_at->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($myStuckAtCheckout as $lead)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $lead->name }}</td>
                                <td class="px-4 py-3 text-gray-700">Reached checkout, hasn't paid — call them</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->visibilityAuditFunnelEvents->first()?->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($myStuckAtLanding as $lead)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $lead->name }}</td>
                                <td class="px-4 py-3 text-gray-700">Viewed the offer page, no follow-up yet — call them</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->visibilityAuditFunnelEvents->first()?->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($myUnansweredReplies as $touch)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $touch->lead->name }}</td>
                                <td class="px-4 py-3 text-gray-700">Replied on WhatsApp — no response from you yet</td>
                                <td class="px-4 py-3 text-gray-600">{{ $touch->occurred_at->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('leads.show', $touch->lead) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                </td>
                            </tr>
                        @endforeach
                        @if ($myGapCount === 0)
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Nothing outstanding on your own leads.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">
                Your message log ({{ $myMessageLog->count() }})
            </h3>
            <p class="mb-2 text-xs text-gray-500">The AI-WhatsApp sends to your own leads — most recent first.</p>
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Sent at</th>
                            <th class="px-4 py-3">Lead</th>
                            <th class="px-4 py-3">Message type</th>
                            <th class="px-4 py-3">Outcome</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($myMessageLog as $touch)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $touch->occurred_at?->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $touch->lead?->name ?? 'Lead removed' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $touch->touch_type?->label() }}</td>
                                <td class="px-4 py-3">
                                    @if ($touch->success)
                                        <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Sent</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Failed</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($touch->lead)
                                        <a href="{{ route('leads.show', $touch->lead) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No AI-WhatsApp sends to your leads yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
