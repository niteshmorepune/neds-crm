<x-app-layout>
    <x-slot name="header">New Content Piece — {{ $project->name }}</x-slot>

    <div class="max-w-4xl mx-auto">
        <form method="POST" action="{{ route('projects.content.store', $project) }}" class="rounded-lg bg-white p-6 shadow-sm space-y-6">
            @csrf
            @include('content-pieces._form', ['contentPiece' => new App\Models\ContentPiece])
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('projects.content.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Add Content Piece</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
