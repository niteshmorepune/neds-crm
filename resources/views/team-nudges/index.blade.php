<x-app-layout>
    <x-slot name="header">Team Nudges</x-slot>

    <div class="max-w-6xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            Reminders shown on the recipient's own Dashboard. A person can snooze their own view,
            but the completion status below always reflects reality regardless of any snooze.
        </p>

        <div class="flex items-center justify-end">
            @can('create', App\Models\TeamNudge::class)
                <a href="{{ route('team-nudges.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">+ New Nudge</a>
            @endcan
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Target</th>
                        <th class="px-4 py-3">Recurrence</th>
                        <th class="px-4 py-3">Auto-detect</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($nudges as $nudge)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $nudge->title }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $nudge->target_role?->label() ?? 'Everyone' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $nudge->recurrence->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $nudge->auto_detect_type?->label() ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $nudge->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $nudge->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('update', $nudge)
                                    <a href="{{ route('team-nudges.edit', $nudge) }}" class="text-indigo-600 hover:underline">Edit</a>
                                @endcan
                                @can('delete', $nudge)
                                    <form method="POST" action="{{ route('team-nudges.destroy', $nudge) }}" class="inline ml-3"
                                          onsubmit="return confirm('Remove this nudge? Its completion history goes with it.')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:text-red-500">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No nudges yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <livewire:team-nudge-overview />
    </div>
</x-app-layout>
