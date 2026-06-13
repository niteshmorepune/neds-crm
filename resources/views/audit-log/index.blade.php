<x-app-layout>
    <x-slot name="header">Audit Log</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="subject_type" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All record types</option>
                @foreach ($subjectTypes as $type)
                    <option value="{{ $type }}" @selected(($filters['subject_type'] ?? '') === $type)>{{ class_basename($type) }}</option>
                @endforeach
            </select>
            <select name="event" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All events</option>
                @foreach (['created', 'updated', 'deleted'] as $event)
                    <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ ucfirst($event) }}</option>
                @endforeach
            </select>
            <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            @if (array_filter($filters))
                <a href="{{ route('audit-log') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
            @endif
        </form>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Who</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Record</th>
                        <th class="px-4 py-3">Changed fields</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($activities as $activity)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $activity->created_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $activity->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $activity->event === 'created',
                                    'bg-blue-100 text-blue-800' => $activity->event === 'updated',
                                    'bg-red-100 text-red-800' => $activity->event === 'deleted',
                                ])>{{ ucfirst($activity->event) }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</td>
                            <td class="px-4 py-3 text-gray-500">
                                @if (is_array($activity->changes) && $activity->changes !== [])
                                    <span class="text-xs">{{ implode(', ', array_keys($activity->changes)) }}</span>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No activity recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $activities->links() }}</div>
    </div>
</x-app-layout>
