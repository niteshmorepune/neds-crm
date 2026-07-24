<div class="rounded-lg bg-white p-5 shadow-sm">
    <h3 class="text-base font-semibold text-gray-900">Your Productivity This Month</h3>

    @if ($row === null)
        <p class="mt-2 text-sm text-gray-400">No performance data yet this month.</p>
    @elseif ($row['rank'] === null)
        <p class="mt-2 text-sm text-gray-400">{{ $row['ranking_note'] ?? 'Not enough peers in your role yet to compare.' }}</p>
    @else
        <div class="mt-3 flex items-center gap-6">
            <div>
                <p class="text-2xl font-semibold text-indigo-600">#{{ $row['rank'] }}</p>
                <p class="text-xs text-gray-500">of {{ $row['role_group_size'] }} in {{ $row['role'] }}</p>
            </div>
            <div>
                <p class="text-2xl font-semibold text-gray-900">{{ $row['score'] }}<span class="text-sm text-gray-400">/100</span></p>
                <p class="text-xs text-gray-500">Overall score</p>
            </div>
        </div>

        @if ($row['weakest_metric'])
            <p class="mt-3 text-sm text-gray-600">
                Your biggest opportunity this month: <span class="font-medium text-gray-900">{{ str_replace('_', ' ', $row['weakest_metric']) }}</span>
                ({{ $row['weakest_percentile'] }}th percentile among your peers).
            </p>
        @else
            <p class="mt-3 text-sm text-gray-600">Your numbers are fairly even across the board this month — nice consistency.</p>
        @endif

        @if ($aiEnabled)
            <div class="mt-3">
                @if ($tip)
                    <div class="rounded-md border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm text-gray-700">{{ $tip }}</div>
                @else
                    <button type="button" wire:click="getTip" wire:loading.attr="disabled" wire:target="getTip"
                            class="text-sm text-indigo-600 hover:underline disabled:opacity-50">
                        <span wire:loading.remove wire:target="getTip">✨ Get tips to improve</span>
                        <span wire:loading wire:target="getTip">Thinking…</span>
                    </button>
                @endif
                @if ($error)
                    <p class="mt-1 text-xs text-red-600">{{ $error }}</p>
                @endif
            </div>
        @endif
    @endif
</div>
