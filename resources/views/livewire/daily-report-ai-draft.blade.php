<div>
    @if ($aiEnabled)
        <div class="flex items-center gap-2">
            <button type="button" wire:click="draft" wire:loading.attr="disabled" wire:target="draft"
                    class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                <span wire:loading.remove wire:target="draft">✨ Draft with AI</span>
                <span wire:loading wire:target="draft">Drafting…</span>
            </button>
            @if ($error)
                <span class="text-xs text-red-600">{{ $error }}</span>
            @endif
        </div>
        @if ($draftUsageId)
            <x-ai-feedback method="rateDraft" :value="$draftFeedback" />
        @endif
    @endif
</div>
