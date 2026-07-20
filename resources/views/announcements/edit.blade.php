<x-app-layout>
    <x-slot name="header">Edit Announcement</x-slot>

    <div class="max-w-2xl mx-auto">
        <form method="POST" action="{{ route('announcements.update', $announcement) }}" class="rounded-lg bg-white p-6 shadow-sm space-y-5">
            @csrf
            @method('PUT')
            @include('announcements._form')
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('announcements.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
