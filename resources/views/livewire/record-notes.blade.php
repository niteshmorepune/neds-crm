<div>
    @if ($canManage || $canAddNotes)
        <div class="mb-4">
            <textarea wire:model="body" rows="3"
                      placeholder="Add a note… use @name to mention a teammate"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('body') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            @if ($draftUsageId)
                <x-ai-feedback method="rateDraft" :value="$draftFeedback" />
            @endif
            <div class="mt-2 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    @if ($showPortalToggle)
                        <label class="flex items-center gap-1.5 text-sm select-none cursor-pointer
                               {{ $visibleToClient ? 'text-indigo-600' : 'text-gray-400' }}">
                            <input type="checkbox" wire:model.live="visibleToClient"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span>Visible to client</span>
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
            <li class="border-l-2 {{ $note->visible_to_client ? 'border-indigo-300' : 'border-gray-200' }} pl-4">
                @if ($editingNoteId === $note->id)
                    {{-- Inline edit form --}}
                    <div class="space-y-2">
                        <textarea wire:model="editBody" rows="3"
                                  class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('editBody') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        @if ($showPortalToggle)
                            <label class="flex items-center gap-1.5 text-sm select-none cursor-pointer
                                   {{ $editVisibleToClient ? 'text-indigo-600' : 'text-gray-400' }}">
                                <input type="checkbox" wire:model.live="editVisibleToClient"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Visible to client
                            </label>
                        @endif
                        <div class="flex items-center gap-2">
                            <x-primary-button wire:click="updateNote" type="button">Save</x-primary-button>
                            <button type="button" wire:click="cancelEdit"
                                    class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                        </div>
                    </div>
                @else
                    <div class="text-sm text-gray-800 whitespace-pre-line">{{ $note->body }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-400">
                        <span>{{ $note->author?->name ?? 'System' }} · {{ $note->created_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</span>
                        @if ($note->visible_to_client)
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">Visible to client</span>
                        @endif
                        @if (($canManage || $canAddNotes) && ($note->user_id === auth()->id() || $canManage))
                            <button type="button" wire:click="startEdit({{ $note->id }})"
                                    class="text-gray-400 hover:text-gray-600 underline">Edit</button>
                            <button type="button" wire:click="deleteNote({{ $note->id }})"
                                    wire:confirm="Delete this note?"
                                    class="text-red-400 hover:text-red-600 underline">Delete</button>
                        @endif
                    </div>
                @endif
            </li>
        @empty
            <li class="text-sm text-gray-400">No notes yet.</li>
        @endforelse
    </ul>
</div>
