<x-app-layout>
    <x-slot name="header">New Subscription</x-slot>

    <div class="max-w-2xl mx-auto">
        <form method="POST" action="{{ route('subscriptions.store') }}" class="rounded-lg bg-white p-6 shadow-sm space-y-5">
            @csrf
            @include('subscriptions._form')
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('subscriptions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Save Subscription</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
