<x-portal-app-layout header="Raise a Ticket">
    <div class="max-w-2xl">
        <form method="POST" action="{{ route('portal.tickets.store') }}" class="rounded-lg bg-white p-6 shadow-sm space-y-4">
            @csrf
            <div>
                <x-input-label for="subject" value="Subject *" />
                <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" :value="old('subject')" required />
                <x-input-error :messages="$errors->get('subject')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="priority" value="Priority *" />
                <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="description" value="Describe the issue *" />
                <textarea id="description" name="description" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1" />
            </div>
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('portal.tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Submit ticket</x-primary-button>
            </div>
        </form>
    </div>
</x-portal-app-layout>
