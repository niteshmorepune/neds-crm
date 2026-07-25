<x-app-layout>
    <x-slot name="header">Edit Team Nudge</x-slot>

    <div class="max-w-2xl mx-auto">
        <form method="POST" action="{{ route('team-nudges.update', $nudge) }}" class="rounded-lg bg-white p-6 shadow-sm space-y-5">
            @csrf @method('PUT')
            @include('team-nudges._form')
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('team-nudges.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
