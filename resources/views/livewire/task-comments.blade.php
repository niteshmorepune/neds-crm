<div>
    @if ($canManage)
        <div class="mb-4">
            <textarea wire:model="body" rows="2" placeholder="Add a commentâ€¦"
                      class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('body') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            <div class="mt-2 flex justify-end">
                <x-primary-button wire:click="addComment" type="button">Comment</x-primary-button>
            </div>
        </div>
    @endif

    <ul class="space-y-3">
        @forelse ($comments as $comment)
            <li class="border-l-2 border-gray-200 pl-3">
                <div class="text-sm text-gray-800 whitespace-pre-line">{{ $comment->body }}</div>
                <div class="mt-0.5 text-xs text-gray-400">{{ $comment->author?->name ?? 'System' }} Â· {{ $comment->created_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</div>
            </li>
        @empty
            <li class="text-sm text-gray-400">No comments yet.</li>
        @endforelse
    </ul>
</div>

