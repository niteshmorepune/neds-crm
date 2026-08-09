<x-app-layout>
    <x-slot name="header">New Expense</x-slot>

    <div class="max-w-2xl mx-auto">
        <form method="POST" action="{{ route('expenses.store') }}" class="rounded-lg bg-white p-6 shadow-sm space-y-5">
            @csrf
            @include('expenses._form')
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('expenses.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Save Expense</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
