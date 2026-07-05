<x-app-layout>
    <x-slot name="header">Employee Performance Report</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $from->format('Y-m') }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.employee-performance.export', ['month' => $from->format('Y-m')]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <p class="text-sm text-gray-500">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</p>

        @livewire('team-performance-summary', ['fromDate' => $from->toDateString(), 'toDate' => $to->toDateString()])

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3 text-right">Tasks done</th>
                        <th class="px-4 py-3 text-right">On-time %</th>
                        <th class="px-4 py-3 text-right">Calls</th>
                        <th class="px-4 py-3 text-right">Leads conv.</th>
                        <th class="px-4 py-3 text-right">Attendance %</th>
                        <th class="px-4 py-3 text-right">Daily reports</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $r['user'] }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $r['role'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['tasks_completed'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['on_time_pct'] !== null ? $r['on_time_pct'].'%' : '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['calls_made'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['leads_converted'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['attendance_pct'] !== null ? $r['attendance_pct'].'%' : '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['daily_reports'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No users.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
