<x-app-layout>
    <x-slot name="header">Visibility Audit Funnel — {{ $stageLabel }}</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('reports.visibility-audit-funnel', ['from' => $fromInput, 'to' => $toInput]) }}" class="text-sm font-medium text-indigo-600 hover:underline">← Back to funnel dashboard</a>
        </div>

        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">
                {{ $stageLabel }} ({{ $leads->count() }})
            </h3>
            <p class="mb-3 text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($fromInput)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($toInput)->format('d M Y') }}</p>
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Lead</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($leads as $lead)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $lead->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->phone }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->status?->label() ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $lead->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('leads.show', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Open lead →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No leads at this stage in this window.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
