<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Common to every role --}}
        <livewire:attendance-widget />

        <div class="rounded-lg bg-white p-4 shadow-sm flex items-center justify-between">
            <span class="text-sm text-gray-600">End of day? Submit your daily report.</span>
            <a href="{{ route('daily-reports.index') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Daily report</a>
        </div>

        {{-- Role-specific panel --}}
        @include('dashboard.partials.'.$panel, $panelData)
    </div>
</x-app-layout>
