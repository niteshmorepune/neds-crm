<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <livewire:attendance-widget />

        <div class="rounded-lg bg-white p-4 shadow-sm flex items-center justify-between">
            <span class="text-sm text-gray-600">End of day? Submit your daily report.</span>
            <a href="{{ route('daily-reports.index') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Daily report</a>
        </div>

        @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager, \App\Enums\UserRole::Sales))
            <livewire:overdue-follow-ups />
        @endif
    </div>
</x-app-layout>
