<div>
    <x-input-label for="lost_reason" value="Why was this deal lost? *" />
    <select id="lost_reason" name="lost_reason" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        {{-- Deliberately never pre-selects the AI's suggestion (only `old()`
             does, so a failed submit re-shows what was actually picked) --
             real incident, Pipeline Playbook gap idea #2: a pre-filled
             dropdown let a rep save the whole form without ever engaging
             with this field, since `required_if:stage,lost` was already
             satisfied by the untouched AI guess. The suggestion still shows
             as a clearly labeled hint below; picking it now takes a real
             click, same as the Kanban board's own Lost-column picker
             already required. --}}
        <option value="">—</option>
        @foreach ($reasons as $reason)
            <option value="{{ $reason->value }}" @selected(old('lost_reason') === $reason->value)>
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
