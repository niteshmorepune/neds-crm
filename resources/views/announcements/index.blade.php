<x-app-layout>
    <x-slot name="header">Notice Board</x-slot>

    <div class="max-w-5xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            Posts here show as a banner on the staff Dashboard, the Client Portal home page, or
            both, depending on audience — and only within their start/end window.
        </p>

        <div class="flex items-center justify-end">
            @can('create', App\Models\Announcement::class)
                <a href="{{ route('announcements.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">+ New Announcement</a>
            @endcan
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Audience</th>
                        <th class="px-4 py-3">Window</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($announcements as $announcement)
                        @php
                            $status = match (true) {
                                $announcement->starts_at->isFuture() => ['label' => 'Upcoming', 'classes' => 'bg-indigo-50 text-indigo-700'],
                                $announcement->ends_at && $announcement->ends_at->isPast() => ['label' => 'Expired', 'classes' => 'bg-gray-100 text-gray-600'],
                                default => ['label' => 'Active', 'classes' => 'bg-emerald-50 text-emerald-700'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                @if ($announcement->is_pinned)
                                    <span class="mr-1" title="Pinned">📌</span>
                                @endif
                                {{ $announcement->title }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $announcement->audience->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $announcement->starts_at->format('d M Y, h:i A') }}
                                &rarr;
                                {{ $announcement->ends_at?->format('d M Y, h:i A') ?? 'No expiry' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $status['classes'] }}">{{ $status['label'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('update', $announcement)
                                    <a href="{{ route('announcements.edit', $announcement) }}" class="text-indigo-600 hover:underline">Edit</a>
                                @endcan
                                @can('delete', $announcement)
                                    <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" class="inline ml-3"
                                          onsubmit="return confirm('Remove this announcement?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:text-red-500">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No announcements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
