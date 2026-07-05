<x-app-layout>
    <x-slot name="header">Festivals</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
            Only fixed-date national holidays are pre-loaded. Lunar/regional festivals
            (Diwali, Holi, Ganesh Chaturthi, Eid, Navratri, Gudi Padwa, etc.) shift every
            year — add them here yourself from an official calendar before relying on the
            dashboard reminder or client greeting drafts for them.
        </div>

        {{-- Add a festival --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Add a festival</h2>
            <form method="POST" action="{{ route('festivals.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1 min-w-48">
                    <x-input-label for="name" value="Name *" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div class="w-40">
                    <x-input-label for="date" value="Date *" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date')" required />
                </div>
                <div class="flex-1 min-w-48">
                    <x-input-label for="notes" value="Notes" />
                    <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes')" />
                </div>
                <x-primary-button>Add</x-primary-button>
            </form>
        </div>

        {{-- Existing festivals (inline edit; row inputs bind to the forms below via form="…") --}}
        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3 w-40">Date</th>
                        <th class="px-4 py-3">Notes</th>
                        <th class="px-4 py-3 w-28">Active</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($festivals as $festival)
                        <tr>
                            <td class="px-4 py-3">
                                <input form="fest-update-{{ $festival->id }}" name="name" type="text" value="{{ $festival->name }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" required />
                            </td>
                            <td class="px-4 py-3">
                                <input form="fest-update-{{ $festival->id }}" name="date" type="date" value="{{ $festival->date->toDateString() }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" required />
                            </td>
                            <td class="px-4 py-3">
                                <input form="fest-update-{{ $festival->id }}" name="notes" type="text" value="{{ $festival->notes }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            </td>
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-2">
                                    <input form="fest-update-{{ $festival->id }}" type="checkbox" name="is_active" value="1" @checked($festival->is_active) class="rounded border-gray-300 text-indigo-600" />
                                    <span class="text-xs text-gray-500">{{ $festival->is_active ? 'Active' : 'Off' }}</span>
                                </label>
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <button form="fest-update-{{ $festival->id }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Save</button>
                                <button form="fest-delete-{{ $festival->id }}" class="text-xs text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No festivals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- One update + delete form per row, referenced by the inputs above. --}}
        @foreach ($festivals as $festival)
            <form id="fest-update-{{ $festival->id }}" method="POST" action="{{ route('festivals.update', $festival) }}" class="hidden">@csrf @method('PUT')</form>
            <form id="fest-delete-{{ $festival->id }}" method="POST" action="{{ route('festivals.destroy', $festival) }}" class="hidden" onsubmit="return confirm('Remove {{ $festival->name }}?')">@csrf @method('DELETE')</form>
        @endforeach
    </div>
</x-app-layout>
