<x-app-layout>
    <x-slot name="header">{{ $isManager ? 'Team Attendance' : 'My Attendance' }}</x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('attendance.index', array_filter(['month' => $month->copy()->subMonth()->format('Y-m'), 'user_id' => $isManager ? $viewingUser->id : null])) }}" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm">←</a>
                <span class="text-lg font-semibold text-gray-900">{{ $month->format('F Y') }}</span>
                <a href="{{ route('attendance.index', array_filter(['month' => $month->copy()->addMonth()->format('Y-m'), 'user_id' => $isManager ? $viewingUser->id : null])) }}" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm">→</a>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($isManager)
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}" />
                        <select name="user_id" class="rounded-md border-gray-300 text-sm shadow-sm" onchange="this.form.submit()">
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($u->id === $viewingUser->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <a href="{{ route('attendance.export', array_filter(['month' => $month->format('Y-m'), 'user_id' => $isManager ? $viewingUser->id : null])) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
                @can('correct', \App\Models\Attendance::class)
                    <a href="{{ route('attendance.import') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Import from Hitech</a>
                    <a href="{{ route('attendance.corrections') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Corrections</a>
                @endcan
            </div>
        </div>

        @if ($isManager)
            <div class="rounded-md bg-indigo-50 border border-indigo-100 px-4 py-2 text-sm text-indigo-700">
                Viewing: <strong>{{ $viewingUser->name }}</strong>
            </div>
        @endif

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">In</th><th class="px-4 py-3">Out</th><th class="px-4 py-3">Notes</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @for ($day = $month->copy()->startOfMonth(); $day->lte($month->copy()->endOfMonth()) && $day->lte(now()); $day->addDay())
                        @php($rec = $records->get($day->toDateString()))
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-700">{{ $day->format('d M (D)') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $rec?->status?->label() ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $rec?->check_in_at?->timezone(config('app.display_timezone'))->format('g:i A') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $rec?->check_out_at?->timezone(config('app.display_timezone'))->format('g:i A') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $rec?->notes ?? '' }}</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
