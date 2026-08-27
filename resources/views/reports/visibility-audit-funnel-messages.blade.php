<x-app-layout>
    <x-slot name="header">Visibility Audit Funnel — Message Log</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('reports.visibility-audit-funnel', ['from' => $fromInput, 'to' => $toInput]) }}" class="text-sm font-medium text-indigo-600 hover:underline">← Back to funnel dashboard</a>
        </div>

        <p class="text-sm text-gray-500">
            Every AI-WhatsApp send attempt to a Visibility Audit lead — when it was sent, which message, and whether it succeeded.
            Use this to check nothing has been missed: a <span class="font-medium text-red-700">Failed</span> row needs a manual follow-up call or WhatsApp message.
        </p>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="date" name="from" value="{{ $fromInput }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
            <span class="text-xs text-gray-400">to</span>
            <input type="date" name="to" value="{{ $toInput }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
            <select name="type" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All message types</option>
                @foreach ($touchTypes as $type)
                    <option value="{{ $type->value }}" @selected($typeFilter === $type)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <select name="outcome" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All outcomes</option>
                <option value="success" @selected($outcomeFilter === 'success')>Sent</option>
                <option value="failed" @selected($outcomeFilter === 'failed')>Failed</option>
            </select>
            <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            @if ($typeFilter || $outcomeFilter)
                <a href="{{ route('reports.visibility-audit-funnel.messages', ['from' => $fromInput, 'to' => $toInput]) }}" class="text-sm text-gray-500 hover:text-gray-700">Reset filters</a>
            @endif
        </form>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Sent at</th>
                        <th class="px-4 py-3">Lead</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Message type</th>
                        <th class="px-4 py-3">Outcome</th>
                        <th class="px-4 py-3">Detail</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($touches as $touch)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $touch->occurred_at?->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $touch->lead?->name ?? 'Lead removed' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $touch->lead?->phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $touch->touch_type?->label() }}</td>
                            <td class="px-4 py-3">
                                @if ($touch->success)
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Sent</span>
                                @else
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Failed</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                @if (is_array($touch->meta) && $touch->meta !== [])
                                    @if (! $touch->success && isset($touch->meta['error']))
                                        {{ Str::limit($touch->meta['error'], 80) }}
                                    @elseif (! $touch->success && isset($touch->meta['status']))
                                        HTTP {{ $touch->meta['status'] }}
                                    @elseif (isset($touch->meta['template']))
                                        {{ $touch->meta['template'] }}
                                    @else
                                        {{ Str::limit(json_encode($touch->meta), 80) }}
                                    @endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                @if ($touch->lead)
                                    <a href="{{ route('leads.show', $touch->lead) }}" class="inline-block whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No AI-WhatsApp sends in this window.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $touches->links() }}</div>
    </div>
</x-app-layout>
