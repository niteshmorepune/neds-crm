<x-app-layout>
    <x-slot name="header">Edit Lead</x-slot>

    <div class="max-w-4xl mx-auto">
        <form method="POST" action="{{ route('leads.update', $lead) }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('leads._form')
            <div class="mt-6 flex items-center justify-between gap-3">
                @can('delete', $lead)
                    <button type="submit" form="delete-lead" class="text-sm font-medium text-red-600 hover:text-red-500"
                            onclick="return confirm('Delete this lead?')">Delete lead</button>
                @else
                    <span></span>
                @endcan
                <div class="flex items-center gap-3">
                    <a href="{{ route('leads.show', $lead) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    <x-primary-button>Save Changes</x-primary-button>
                </div>
            </div>
        </form>

        @can('delete', $lead)
            <form id="delete-lead" method="POST" action="{{ route('leads.destroy', $lead) }}" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endcan
    </div>
</x-app-layout>
