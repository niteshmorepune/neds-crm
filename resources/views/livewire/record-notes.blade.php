<div>
    @if ($canManage)
        <div class="mb-4">
            <textarea wire:model="body" rows="3"
                      placeholder="Add a noteâ€¦ use @name to mention a teammate"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('body') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            <div class="mt-2 flex items-center justify-end gap-2">
                @if ($canDraft)
                    <button type="button" wire:click="draftFollowUp" wire:loading.attr="disabled" wire:target="draftFollowUp"
                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        <span wire:loading.remove wire:target="draftFollowUp">âœ¨ Draft follow-up</span>
                        <span wire:loading wire:target="draftFollowUp">Draftingâ€¦</span>
                    </button>
                @endif
                <x-primary-button wire:click="addNote" type="button">Add note</x-primary-button>
            </div>
        </div>
    @endif

    <ul class="space-y-4">
        @forelse ($notes as $note)
            <li class="border-l-2 border-gray-200 pl-4">
                <div class="text-sm text-gray-800 whitespace-pre-line">{{ $note->body }}</div>
                <div class="mt-1 text-xs text-gray-400">
                    {{ $note->author?->name ?? 'System' }} Â· {{ $note->created_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}
                </div>
            </li>
        @empty
            <li class="text-sm text-gray-400">No notes yet.</li>
        @endforelse
    </ul>
</div>

