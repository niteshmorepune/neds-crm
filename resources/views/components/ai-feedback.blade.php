@props(['method', 'value' => null])

@if ($value)
    <p class="mt-1.5 text-xs text-gray-400">Thanks for the feedback.</p>
@else
    <div class="mt-1.5 flex items-center gap-2 text-xs text-gray-400">
        <span>Was this useful?</span>
        <button type="button" wire:click="{{ $method }}('up')" class="font-medium text-gray-500 hover:text-green-600">Helpful</button>
        <span aria-hidden="true">&middot;</span>
        <button type="button" wire:click="{{ $method }}('down')" class="font-medium text-gray-500 hover:text-red-600">Not helpful</button>
    </div>
@endif
