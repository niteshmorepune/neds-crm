<div>
    @if ($pendingDrafts->isNotEmpty())
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-1 text-base font-semibold text-gray-900">✨ Pending Client Update{{ $pendingDrafts->count() > 1 ? 's' : '' }}</h2>
            <p class="mb-4 text-xs text-gray-400">AI-drafted from today's completed tasks — review, edit if needed, then approve to share with the client (portal + email).</p>
            <ul class="space-y-4">
                @foreach ($pendingDrafts as $draft)
                    <li class="rounded-md border border-amber-200 bg-amber-50 p-4">
                        <textarea wire:model="editedBody.{{ $draft->id }}" rows="3"
                                  class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $draft->body }}</textarea>
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-400">Drafted {{ $draft->created_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</span>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="discard({{ $draft->id }})"
                                        wire:confirm="Discard this draft? It will not be shown to the client."
                                        class="text-sm text-red-600 hover:text-red-500">Discard</button>
                                <x-primary-button wire:click="approve({{ $draft->id }})" type="button">Approve &amp; Send</x-primary-button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
