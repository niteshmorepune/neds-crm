<x-app-layout>
    <x-slot name="header">Attendance Corrections</x-slot>

    <div class="max-w-5xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Date</label>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="rounded-md border-gray-300 text-sm shadow-sm" onchange="this.form.submit()" />
            <span class="text-xs text-gray-400">Corrections are logged to the activity log.</span>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Current</th><th class="px-4 py-3">Mark as</th><th class="px-4 py-3">Notes</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($users as $person)
                        @php($e = $entries->get($person->id))
                        <tr>
                            <form method="POST" action="{{ route('attendance.corrections.store') }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $person->id }}" />
                                <input type="hidden" name="date" value="{{ $date->toDateString() }}" />
                                <td class="px-4 py-2 text-gray-700">{{ $person->name }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">
                                    @if ($e)
                                        {{ $e->status->label() }}
                                        @if ($e->check_in_at) · in {{ $e->check_in_at->timezone(config('app.display_timezone'))->format('g:i A') }} @endif
                                        @if ($e->check_out_at) · out {{ $e->check_out_at->timezone(config('app.display_timezone'))->format('g:i A') }} @endif
                                    @else
                                        Not marked
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->value }}" @selected($e?->status === $status)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2"><input type="text" name="notes" value="{{ $e?->notes }}" class="rounded-md border-gray-300 text-sm shadow-sm w-full" /></td>
                                <td class="px-4 py-2"><button class="rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">Save</button></td>
                            </form>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
