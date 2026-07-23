<div @if (! in_array($status, ['completed', 'failed'], true)) wire:poll.4s="refreshStatus" @endif>
    @if ($audioUrl)
        <audio controls preload="none" class="h-7 max-w-[190px] align-middle"><source src="{{ $audioUrl }}"></audio>
    @endif
    @if ($status === 'pending' || $status === 'processing')
        <span class="inline-flex items-center gap-1 text-xs text-gray-400">
            <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            🎙️ Transcribing…
        </span>
    @elseif ($status === 'completed' && $transcript)
        <div class="mt-1 rounded-md border border-indigo-100 bg-indigo-50 px-2 py-1">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-indigo-500">🎙️ Voice note</span>
            <p class="mt-0.5 text-xs text-gray-600">{{ $transcript }}</p>
        </div>
    @elseif ($status === 'failed')
        <span class="text-xs text-gray-400">🎙️ Transcription failed</span>
    @endif
</div>
