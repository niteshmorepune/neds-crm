<x-app-layout>
    <x-slot name="header">Team WFH Records</x-slot>

    <div class="max-w-6xl mx-auto space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('work-from-home.approvals') }}" class="text-sm text-indigo-600 hover:underline">← Back to pending approvals</a>
        </div>

        @include('work-from-home._summary')

        <form method="GET" class="flex flex-wrap items-end gap-2 rounded-lg bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Employee</label>
                <select name="user_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $employee->id)>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Duration</label>
                <select name="type" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All durations</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
            </div>
            <button type="submit" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            @if (array_filter($filters))
                <a href="{{ route('work-from-home.team') }}" class="text-sm text-gray-500 hover:underline">Clear</a>
            @endif
        </form>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Duration</th>
                        <th class="px-4 py-3">From</th>
                        <th class="px-4 py-3">To</th>
                        <th class="px-4 py-3">Days</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3">Applied on</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Approved/Rejected by</th>
                        <th class="px-4 py-3">Action date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($requests as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-700">{{ $r->user?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->type->label() }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->start_date->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->end_date->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ rtrim(rtrim(number_format($r->dayCount(), 1), '0'), '.') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->reason }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r->created_at->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('d M Y') }}</td>
                            <td class="px-4 py-2">
                                @php($color = match ($r->status->value) {
                                    'approved' => 'bg-green-50 text-green-700 border-green-200',
                                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                    'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
                                    default => 'bg-amber-50 text-amber-700 border-amber-200',
                                })
                                <span class="inline-block rounded-full border px-2 py-0.5 text-xs font-medium {{ $color }}">{{ $r->status->label() }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r->reviewer?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">
                                {{ $r->reviewed_at?->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('d M Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-6 text-center text-gray-400">No WFH records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $requests->links() }}</div>
    </div>
</x-app-layout>
