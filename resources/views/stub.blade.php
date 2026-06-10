<x-app-layout>
    <x-slot name="header">
        {{ $title }}
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-700">
                <h1 class="text-lg font-semibold text-gray-900">{{ $title }}</h1>
                <p class="mt-2 text-sm text-gray-500">
                    This section is part of the NEDS CRM and will be built in an upcoming milestone.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
