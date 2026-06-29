<x-app-layout>
    <x-slot name="header">Edit Content Piece</x-slot>

    <div class="max-w-4xl mx-auto">
        <form method="POST" action="{{ route('content.update', $contentPiece) }}" class="rounded-lg bg-white p-6 shadow-sm space-y-6">
            @csrf
            @method('PUT')
            @include('content-pieces._form')
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('content.show', $contentPiece) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
