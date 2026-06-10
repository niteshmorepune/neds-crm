<x-app-layout>
    <x-slot name="header">Edit Task</x-slot>

    <div class="max-w-3xl mx-auto">
        <form method="POST" action="{{ route('tasks.update', $task) }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('tasks._form')
            <div class="mt-6 flex items-center justify-between gap-3">
                @can('delete', $task)
                    <button type="submit" form="delete-task" class="text-sm font-medium text-red-600 hover:text-red-500" onclick="return confirm('Delete this task?')">Delete</button>
                @else
                    <span></span>
                @endcan
                <div class="flex items-center gap-3">
                    <a href="{{ route('tasks.show', $task) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    <x-primary-button>Save Changes</x-primary-button>
                </div>
            </div>
        </form>
        @can('delete', $task)
            <form id="delete-task" method="POST" action="{{ route('tasks.destroy', $task) }}" class="hidden">@csrf @method('DELETE')</form>
        @endcan
    </div>
</x-app-layout>
