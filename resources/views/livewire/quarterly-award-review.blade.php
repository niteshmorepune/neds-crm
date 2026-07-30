<div class="space-y-4">
    @if ($error)
        <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ $error }}</p>
    @endif

    @forelse ($pendingAwards as $award)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4" wire:key="award-{{ $award->id }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $award->title() }}</p>
                    <p class="text-xs text-gray-500">{{ $award->periodLabel() }} &middot; AI score {{ $award->score }}/100</p>
                </div>
                <span class="rounded-full bg-amber-200 px-2 py-0.5 text-xs font-medium text-amber-800">Pending review</span>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600">Winner</label>
                    <select wire:model="forms.{{ $award->id }}.user_id"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        @foreach ($eligiblePeers[$award->id] ?? [] as $peer)
                            <option value="{{ $peer->id }}">{{ $peer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-600">Citation (editable)</label>
                <textarea wire:model="forms.{{ $award->id }}.citation" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                          placeholder="AI could not draft a citation — write one before approving."></textarea>
            </div>

            <div class="mt-3 flex gap-2">
                <button type="button" wire:click="approve({{ $award->id }})" wire:loading.attr="disabled"
                        class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50">
                    Approve &amp; Announce
                </button>
                <button type="button" wire:click="reject({{ $award->id }})" wire:loading.attr="disabled"
                        wire:confirm="Reject this award suggestion for this quarter?"
                        class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50">
                    Reject
                </button>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">No pending award suggestions right now.</p>
    @endforelse
</div>
