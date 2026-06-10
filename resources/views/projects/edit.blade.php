<x-app-layout>
    <x-slot name="header">Edit Project</x-slot>

    <div class="max-w-4xl mx-auto">
        <form method="POST" action="{{ route('projects.update', $project) }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('projects._form')
            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
