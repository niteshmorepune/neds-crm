<x-app-layout>
    <x-slot name="header">Reassignment Analytics</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $from->format('Y-m') }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.reassignment-analytics.export', ['month' => $from->format('Y-m')]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <div class="rounded-lg bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Leads reassigned</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $data['total'] }}</p>
            <p class="text-xs text-gray-400">{{ $from->format('M Y') }}</p>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">Rep</th>
                        <th class="py-2 text-right">Reassigned away</th>
                        <th class="py-2">Reasons</th>
                        <th class="py-2 text-right">Reassigned to them</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['rows'] as $r)
                        <tr>
                            <td class="py-2 font-medium text-gray-800">{{ $r['user'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['reassigned_away_count'] }}</td>
                            <td class="py-2 text-gray-600">
                                @if (count($r['reassigned_away_reasons']) > 0)
                                    {{ collect($r['reassigned_away_reasons'])->map(fn ($x) => "{$x['label']} ({$x['count']})")->implode(', ') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 text-right text-gray-600">{{ $r['reassigned_to_count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-400">No active Sales reps to show.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
