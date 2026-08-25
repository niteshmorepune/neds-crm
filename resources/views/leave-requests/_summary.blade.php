@php($tiles = [
    ['label' => 'Pending', 'value' => $summary['pending'], 'classes' => 'bg-amber-50 text-amber-700'],
    ['label' => 'Approved this month', 'value' => $summary['approved_this_month'], 'classes' => 'bg-green-50 text-green-700'],
    ['label' => 'Rejected this month', 'value' => $summary['rejected_this_month'], 'classes' => 'bg-red-50 text-red-700'],
    ['label' => 'Currently on leave', 'value' => $summary['currently_on_leave'], 'classes' => 'bg-indigo-50 text-indigo-700'],
])
<dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach ($tiles as $tile)
        <div class="rounded-lg {{ $tile['classes'] }} px-4 py-3">
            <dt class="text-xs font-medium">{{ $tile['label'] }}</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ number_format($tile['value']) }}</dd>
        </div>
    @endforeach
</dl>
