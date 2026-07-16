@props(['stageConversion'])

<div {{ $attributes->merge(['class' => 'rounded-lg bg-white p-4 shadow-sm']) }}>
    <p class="mb-2 text-xs font-medium text-gray-500">Stage conversion</p>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($stageConversion as $pair)
            <div class="text-sm">
                <span class="text-gray-500">{{ $pair['from']->label() }} → {{ $pair['to']->label() }}</span>
                <span class="ml-1 font-semibold {{ $pair['rate'] !== null ? 'text-gray-900' : 'text-gray-300' }}">
                    {{ $pair['rate'] !== null ? $pair['rate'].'%' : 'Not enough data yet' }}
                </span>
            </div>
        @endforeach
    </div>
</div>
