<x-app-layout>
    <x-slot name="header">Edit User</x-slot>

    <div class="max-w-xl mx-auto space-y-6">
        <form method="POST" action="{{ route('users.update', $user) }}" class="rounded-lg bg-white p-6 shadow-sm">
            @method('PUT')
            @include('users._form')
        </form>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900 mb-1">Internal Notes</h2>
            <p class="text-xs text-gray-400 mb-4">Feedback, areas of improvement, follow-up actions — visible only here, never to the employee or anyone outside Users access.</p>
            <livewire:record-notes :record="$user" :can-manage="true" />
        </div>
    </div>
</x-app-layout>
