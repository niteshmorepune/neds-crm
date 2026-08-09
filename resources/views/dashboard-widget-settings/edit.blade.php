<x-app-layout>
    <x-slot name="header">Customize dashboard</x-slot>

    <div class="max-w-2xl mx-auto space-y-4">
        <p class="text-sm text-gray-500">Choose which cards and sections show on your own Dashboard. This only affects your view — nobody else's dashboard changes.</p>

        <form method="POST" action="{{ route('dashboard-widget-settings.update') }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            <div class="space-y-2">
                @forelse ($widgets as $key => $label)
                    <label class="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-gray-50">
                        <input type="checkbox" name="visible[]" value="{{ $key }}" @checked(! in_array($key, $hidden)) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-400">No customizable widgets on your dashboard.</p>
                @endforelse
            </div>

            @if (count($widgets) > 0)
                <div class="mt-6 flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Save</button>
                    <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            @endif
        </form>
    </div>
</x-app-layout>
