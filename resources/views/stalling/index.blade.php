<x-app-layout>
    <x-slot name="header">Stalling</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
            Every open lead and deal currently tagged with a stall reason — tag one from a lead's or deal's own page, or from the Log a Call form. Once tagged, if nobody touches it for 3 days it also surfaces in your Next Action banner by name.
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Your stalling leads &amp; deals ({{ $mine->count() }})
                </h3>
            </div>
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">Last touch</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($mine as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $row['name'] }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $row['subject_type'] === \App\Models\Lead::class ? 'Lead' : 'Deal' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ $row['stall_reason']->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ $row['days_stale'] }} day{{ $row['days_stale'] === 1 ? '' : 's' }} ago
                                    @if ($row['days_stale'] >= 3)
                                        <span class="ml-1 text-amber-600" title="Stale enough to show in the Next Action banner">⚠</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ $row['url'] }}" class="text-indigo-600 hover:underline">Open →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Nothing tagged as stalling right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($isManager)
            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    By reason, whole team ({{ $team->count() }} total)
                </h3>
                <div class="flex flex-wrap gap-3">
                    @forelse ($countsByReason as $value => $count)
                        <div class="rounded-lg bg-white px-4 py-3 shadow-sm">
                            <div class="text-xl font-semibold text-gray-900">{{ $count }}</div>
                            <div class="text-xs text-gray-500">{{ \App\Enums\StallReason::from($value)->label() }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Nothing tagged as stalling right now.</p>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Whole team
                </h3>
                <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Owner</th>
                                <th class="px-4 py-3">Reason</th>
                                <th class="px-4 py-3">Last touch</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($team as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row['name'] }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $row['subject_type'] === \App\Models\Lead::class ? 'Lead' : 'Deal' }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $row['owner_name'] ?? 'Unassigned' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ $row['stall_reason']->label() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $row['days_stale'] }} day{{ $row['days_stale'] === 1 ? '' : 's' }} ago</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ $row['url'] }}" class="text-indigo-600 hover:underline">Open →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Nothing tagged as stalling right now.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
