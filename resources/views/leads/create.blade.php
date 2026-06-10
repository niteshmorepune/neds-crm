<x-app-layout>
    <x-slot name="header">Add Lead</x-slot>

    <div class="max-w-4xl mx-auto">
        <form method="POST" action="{{ route('leads.store') }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf
            @include('leads._form')
            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('leads.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Create Lead</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
