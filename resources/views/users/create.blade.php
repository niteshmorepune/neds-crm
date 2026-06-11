<x-app-layout>
    <x-slot name="header">Add User</x-slot>

    <div class="max-w-xl mx-auto">
        <form method="POST" action="{{ route('users.store') }}" class="rounded-lg bg-white p-6 shadow-sm">
            @include('users._form')
        </form>
    </div>
</x-app-layout>
