<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <livewire:attendance-widget />

        @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager, \App\Enums\UserRole::Sales))
            <livewire:overdue-follow-ups />
        @endif
    </div>
</x-app-layout>
