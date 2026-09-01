<div>
    <x-input-label for="lost_reason" value="Why was this deal lost? *" />
    <select id="lost_reason" name="lost_reason" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        <option value="">—</option>
        @foreach ($reasons as $reason)
            <option value="{{ $reason->value }}" @selected(old('lost_reason', $suggestedReason) === $reason->value)>
                {{ $reason->label() }}{{ $suggestedReason === $reason->value ? ' (suggested)' : '' }}
            </option>
        @endforeach
    </select>

    <p wire:loading wire:target="suggest" class="mt-1 text-xs text-gray-400">
        Checking notes for a suggestion&hellip;
    </p>

    <div wire:loading.remove wire:target="suggest">
        @if ($rationale)
            <p class="mt-1 text-xs text-indigo-600">✨ {{ $rationale }}</p>
        @elseif ($requested && $suggestedReason === null)
            <p class="mt-1 text-xs text-gray-400">No suggestion available — pick the one that fits.</p>
        @endif
    </div>
</div>
