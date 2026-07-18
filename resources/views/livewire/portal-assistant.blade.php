<div>
    @if ($aiEnabled)
        <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-100 mb-6">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15.75l-.75.75-.75-.75M12.75 15l1.5 1.5 1.5-1.5M9 12h6m-9 8.25h12A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Ask about your account</h2>
            </div>
            <p class="text-xs text-gray-400 mb-3">e.g. "When's my next payment due?" or "What's the status of my SEO project?"</p>

            <form wire:submit="ask" class="flex flex-col sm:flex-row gap-2">
                <input type="text" wire:model="question" maxlength="300" placeholder="Type your question…"
                       class="flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       @disabled($rateLimited)>
                <button type="submit" wire:loading.attr="disabled" wire:target="ask" @disabled($rateLimited)
                        class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    <span wire:loading.remove wire:target="ask">Ask</span>
                    <span wire:loading wire:target="ask">Thinking…</span>
                </button>
            </form>
            @error('question')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror

            @if ($rateLimited)
                <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    You've reached today's question limit. For anything else, please
                    <a href="{{ route('portal.tickets.create') }}" class="font-medium underline">raise a support ticket</a>
                    or contact your account manager.
                </div>
            @endif

            @if ($answer)
                <div class="mt-3 rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3 text-sm text-indigo-900">
                    <div class="flex items-start justify-between gap-3">
                        <p>{{ $answer }}</p>
                        <button type="button" wire:click="dismiss" class="shrink-0 text-indigo-400 hover:text-indigo-600" aria-label="Dismiss">&times;</button>
                    </div>
                    <x-ai-feedback method="rateAnswer" :value="$answerFeedback" />
                </div>
            @endif
        </div>
    @endif
</div>
