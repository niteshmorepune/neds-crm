<x-app-layout>
    <x-slot name="header">Edit User</x-slot>

    <div class="max-w-xl mx-auto">
        <form method="POST" action="{{ route('users.update', $user) }}" class="rounded-lg bg-white p-6 shadow-sm">
            @method('PUT')
            @include('users._form')
        </form>
    </div>
</x-app-layout>
