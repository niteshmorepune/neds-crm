<div>
    @if ($aiEnabled)
        <div class="mb-3 flex justify-end">
            <button type="button" wire:click="summarize" wire:loading.attr="disabled" wire:target="summarize"
                    class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                <span wire:loading.remove wire:target="summarize">✨ Summarize activity</span>
                <span wire:loading wire:target="summarize">Summarizing…</span>
            </button>
        </div>

        @if (! is_null($summary))
            <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 p-4">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-indigo-700">AI summary</h3>
                    <button type="button" wire:click="dismissSummary" class="text-xs text-indigo-500 hover:text-indigo-700">Dismiss</button>
                </div>
                <div class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $summary }}</div>
            </div>
        @endif
    @endif

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
                    {{ $note->author?->name ?? 'System' }} · {{ $note->created_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}
                </div>
            </li>
        @empty
            <li class="text-sm text-gray-400">No notes yet.</li>
        @endforelse
    </ul>
</div>
