<div>
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
            <div class="mt-2 flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" wire:model="is_internal" class="rounded border-gray-300 text-indigo-600" />
                    Internal note (not emailed to client)
                </label>
                <x-primary-button wire:click="addReply" type="button">Send reply</x-primary-button>
            </div>
        </div>
    @endif
</div>
