@props(['metric', 'target', 'actual', 'pct'])

<div>
    <div class="flex items-center justify-between text-sm">
        <span class="text-gray-600">{{ $metric->label() }}</span>
        @if ($target !== null)
            <span class="font-medium text-gray-900">
                {{ $metric->isMoney() ? \App\Support\Money::format($actual) : number_format($actual) }}
                / {{ $metric->isMoney() ? \App\Support\Money::format($target) : number_format($target) }}
            </span>
        @else
            <span class="text-gray-300">No target set</span>
        @endif
    </div>
    @if ($target !== null)
        <div class="mt-1 h-2 rounded-full bg-gray-100">
            <div class="h-2 rounded-full {{ ($pct ?? 0) >= 100 ? 'bg-green-500' : 'bg-indigo-500' }}"
                 style="width: {{ min(100, $pct ?? 0) }}%"></div>
        </div>
        <p class="mt-1 text-xs text-gray-400">{{ $pct }}% of target</p>
    @endif
</div>
