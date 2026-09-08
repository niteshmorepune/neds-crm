@props(['record', 'updateRoute', 'reasons'])

{{--
    Phase 1 of the closure-guidance plan (2026-09-08) -- a one-click tag for
    why a lead/deal with real conversation history isn't moving forward.
    Plain onchange-submit, same convention as month-filter.blade.php -- no
    Alpine needed for a single select.
--}}
<form method="POST" action="{{ route($updateRoute, $record) }}" class="inline-flex items-center gap-2">
    @csrf
    <label for="stall_reason_{{ $record->id }}" class="text-xs text-gray-400">Stalling on:</label>
    <select id="stall_reason_{{ $record->id }}" name="stall_reason" onchange="this.form.submit()"
            @class([
                'rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
                'border-amber-300 bg-amber-50 text-amber-800' => $record->stall_reason,
            ])>
        <option value="">— Not stalling —</option>
        @foreach ($reasons as $reason)
            <option value="{{ $reason->value }}" @selected($record->stall_reason === $reason)>{{ $reason->label() }}</option>
        @endforeach
    </select>
</form>
