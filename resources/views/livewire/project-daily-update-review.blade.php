<div>
    @if ($pendingDrafts->isNotEmpty())
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-1 flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-gray-900">✨ Pending Client Update{{ $pendingDrafts->count() > 1 ? 's' : '' }}</h2>
                @if ($pendingDrafts->count() > 1)
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs text-gray-500">
                            <input type="checkbox" wire:click="toggleSelectAll"
                                   @checked(count(array_intersect($selected, $pendingDrafts->pluck('id')->all())) === $pendingDrafts->count())
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            Select all
                        </label>
                        <button type="button" wire:click="discardSelected"
                                wire:confirm="Discard {{ count($selected) }} selected draft{{ count($selected) === 1 ? '' : 's' }}? They will not be shown to the client."
                                @disabled(empty($selected))
                                class="text-sm font-medium text-red-600 hover:text-red-500 disabled:cursor-not-allowed disabled:text-gray-300">
                            Discard selected ({{ count($selected) }})
                        </button>
                    </div>
                @endif
            </div>
            <p class="mb-4 text-xs text-gray-400">AI-drafted from today's completed tasks — review, edit if needed, then approve to share with the client (portal + email).</p>
            <ul class="space-y-4">
                @foreach ($pendingDrafts as $draft)
                    <li class="rounded-md border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-start gap-3">
                            @if ($pendingDrafts->count() > 1)
                                <input type="checkbox" wire:model="selected" value="{{ $draft->id }}"
                                       class="mt-2 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            @endif
                            <div class="flex-1">
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
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
