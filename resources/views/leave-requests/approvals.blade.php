<x-app-layout>
    <x-slot name="header">Leave Approvals</x-slot>

    <div class="max-w-4xl mx-auto space-y-4" x-data="{ rejecting: null }">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <a href="{{ route('leave-requests.index') }}" class="text-sm text-indigo-600 hover:underline">← Back to my requests</a>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Dates</th>
                        <th class="px-4 py-3">Days</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($requests as $r)
                        <tr>
                            <td class="px-4 py-2 text-gray-700">{{ $r->user?->name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->type->label() }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->start_date->format('d M Y') }} – {{ $r->end_date->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ rtrim(rtrim(number_format($r->dayCount(), 1), '0'), '.') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->reason }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('leave-requests.approve', $r) }}">
                                        @csrf
                                        <button class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-500">Approve</button>
                                    </form>
                                    <button type="button" @click="rejecting = rejecting === {{ $r->id }} ? null : {{ $r->id }}"
                                            class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500">Reject</button>
                                </div>
                                <form x-cloak x-show="rejecting === {{ $r->id }}" method="POST" action="{{ route('leave-requests.reject', $r) }}" class="mt-2 flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="review_notes" placeholder="Reason (optional)" maxlength="255" class="rounded-md border-gray-300 text-xs shadow-sm" />
                                    <button class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Confirm reject</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No pending leave requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
