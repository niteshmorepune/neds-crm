<div>
    @if ($canManage)
        <div class="mb-4">
            <textarea wire:model="body" rows="3"
                      placeholder="Add a note… use @name to mention a teammate"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('body') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            <div class="mt-2 flex justify-end">
                <x-primary-button wire:click="addNote" type="button">Add note</x-primary-button>
            </div>
        </div>
    @endif

    <ul class="space-y-4">
        @forelse ($notes as $note)
            <li class="border-l-2 border-gray-200 pl-4">
                <div class="text-sm text-gray-800 whitespace-pre-line">{{ $note->body }}</div>
                <div class="mt-1 text-xs text-gray-400">
                    {{ $note->author?->name ?? 'System' }} · {{ $note->created_at->timezone(config('app.timezone'))->format('d M Y, g:i A') }}
                </div>
            </li>
        @empty
            <li class="text-sm text-gray-400">No notes yet.</li>
        @endforelse
    </ul>
</div>
