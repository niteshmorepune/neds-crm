<x-app-layout>
    <x-slot name="header">Leave Requests</x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @if ($isManager)
            <div class="flex items-center justify-between rounded-md bg-indigo-50 border border-indigo-100 px-4 py-3 text-sm text-indigo-700">
                <span>Team approvals</span>
                <a href="{{ route('leave-requests.approvals') }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                    Review pending ({{ $pendingCount }})
                </a>
            </div>
        @endif

        <div class="rounded-lg bg-white shadow-sm p-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Apply for Leave</h3>

            @if ($errors->any())
                <div class="mb-3 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('leave-requests.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Start date</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" required class="w-full rounded-md border-gray-300 text-sm shadow-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">End date</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" required class="w-full rounded-md border-gray-300 text-sm shadow-sm" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reason</label>
                    <input type="text" name="reason" value="{{ old('reason') }}" required maxlength="500" class="w-full rounded-md border-gray-300 text-sm shadow-sm" />
                </div>
                <div class="sm:col-span-4">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Submit Request</button>
                </div>
            </form>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Dates</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Reviewer notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($requests as $r)
                        <tr>
                            <td class="px-4 py-2 text-gray-700">{{ $r->start_date->format('d M Y') }} – {{ $r->end_date->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->reason }}</td>
                            <td class="px-4 py-2">
                                @php($color = match ($r->status->value) {
                                    'approved' => 'bg-green-50 text-green-700 border-green-200',
                                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                    default => 'bg-amber-50 text-amber-700 border-amber-200',
                                })
                                <span class="inline-block rounded-full border px-2 py-0.5 text-xs font-medium {{ $color }}">{{ $r->status->label() }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $r->review_notes }}</td>
                            <td class="px-4 py-2">
                                @if ($r->status->value === 'pending')
                                    <form method="POST" action="{{ route('leave-requests.destroy', $r) }}" onsubmit="return confirm('Cancel this leave request?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-gray-400 hover:text-red-500">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No leave requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
