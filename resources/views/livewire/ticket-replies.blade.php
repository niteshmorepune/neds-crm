<div>
    @if ($aiEnabled)
        <div class="mb-3 flex items-center justify-between">
            <button type="button" wire:click="summarize" wire:loading.attr="disabled" wire:target="summarize"
                    class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                <span wire:loading.remove wire:target="summarize">✨ Summarize thread</span>
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

    <ul class="space-y-3">
        @forelse ($replies as $reply)
            <li class="rounded-md border-l-2 pl-3 {{ $reply->is_internal ? 'border-amber-300 bg-amber-50' : ($reply->isFromCustomer() ? 'border-blue-300 bg-blue-50' : 'border-gray-200') }}">
                <div class="py-2">
                    <div class="text-sm text-gray-800 whitespace-pre-line">{{ $reply->body }}</div>
                    <div class="mt-0.5 text-xs text-gray-400">
                        {{ $reply->authorName() }} · {{ $reply->created_at->timezone(config('app.timezone'))->format('d M Y, g:i A') }}
                        @if ($reply->is_internal)<span class="ml-1 font-medium text-amber-600">Internal note</span>
                        @elseif ($reply->isFromCustomer())<span class="ml-1 font-medium text-blue-600">Client</span>@endif
                    </div>
                </div>
            </li>
        @empty
            <li class="text-sm text-gray-400">No replies yet.</li>
        @endforelse
    </ul>

    @if ($canManage)
        <div class="mt-4 border-t border-gray-100 pt-4">
            <textarea wire:model="body" rows="3" placeholder="Write a reply…"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('body') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" wire:model="is_internal" class="rounded border-gray-300 text-indigo-600" />
                    Internal note (not emailed to client)
                </label>
                <div class="flex items-center gap-2">
                    @if ($aiEnabled)
                        <button type="button" wire:click="draftReply" wire:loading.attr="disabled" wire:target="draftReply"
                                class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <span wire:loading.remove wire:target="draftReply">✨ Draft with AI</span>
                            <span wire:loading wire:target="draftReply">Drafting…</span>
                        </button>
                    @endif
                    <x-primary-button wire:click="addReply" type="button">Send reply</x-primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
