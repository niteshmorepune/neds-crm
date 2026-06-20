<div>
    @if ($canManage || $canAddNotes)
        <div class="mb-4">
            <textarea wire:model="body" rows="3"
                      placeholder="Add a note… use @name to mention a teammate"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('body') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            <div class="mt-2 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    @if ($showPortalToggle)
                        <label class="flex items-center gap-1.5 text-sm text-gray-600 select-none cursor-pointer">
                            <input type="checkbox" wire:model="visibleToClient"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            Share with client
                        </label>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if ($canDraft)
                        <button type="button" wire:click="draftFollowUp" wire:loading.attr="disabled" wire:target="draftFollowUp"
                                class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <span wire:loading.remove wire:target="draftFollowUp">✨ Draft follow-up</span>
                            <span wire:loading wire:target="draftFollowUp">Drafting…</span>
                        </button>
                    @endif
                    <x-primary-button wire:click="addNote" type="button">Add note</x-primary-button>
                </div>
            </div>
        </div>
    @endif

    <ul class="space-y-4">
        @forelse ($notes as $note)
            <li class="border-l-2 border-gray-200 pl-4">
                <div class="text-sm text-gray-800 whitespace-pre-line">{{ $note->body }}</div>
                <div class="mt-1 flex items-center gap-2 text-xs text-gray-400">
                    <span>{{ $note->author?->name ?? 'System' }} · {{ $note->created_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</span>
                    @if ($note->visible_to_client)
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">Visible to client</span>
                    @endif
                </div>
            </li>
        @empty
            <li class="text-sm text-gray-400">No notes yet.</li>
        @endforelse
    </ul>
</div>
