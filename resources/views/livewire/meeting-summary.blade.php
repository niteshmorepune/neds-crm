<div @if ($status !== null && ! in_array($status, ['completed', 'failed'], true)) wire:poll.4s="refreshStatus" @endif>
    @if ($summaryFeatureEnabled && $hasTranscript)
        @if ($status === 'pending' || $status === 'processing')
            <span class="mt-1 inline-flex items-center gap-1 text-xs text-gray-400">
                <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                🤖 Summarizing…
            </span>
        @elseif ($status === 'completed' && $summary)
            <div class="mt-2 rounded-md border border-indigo-100 bg-indigo-50 px-3 py-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-indigo-500">🤖 AI summary</span>
                <p class="mt-1 whitespace-pre-wrap text-xs text-gray-700">{{ $summary }}</p>
            </div>
        @elseif ($status === 'failed')
            <div class="mt-1 flex items-center gap-2 text-xs text-gray-400">
                <span>🤖 Summary failed</span>
                @if ($canManage)
                    <button type="button" wire:click="summarize" class="text-indigo-600 hover:underline">Retry</button>
                @endif
            </div>
        @elseif ($canManage)
            <button type="button" wire:click="summarize" wire:loading.attr="disabled" wire:target="summarize"
                    class="mt-1 text-xs text-indigo-600 hover:underline">
                🤖 Summarize with AI
            </button>
        @endif
    @endif
</div>
