<div class="max-w-7xl mx-auto space-y-4">
    <div>
        <h1 class="text-lg font-semibold text-gray-900">Manager Calendar</h1>
        <p class="mt-0.5 text-sm text-gray-500">Meetings, task/project deadlines, and approved leave — one company-wide view.</p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button type="button" wire:click="previousMonth" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">&larr;</button>
            <h2 class="w-40 text-center text-base font-semibold text-gray-900">{{ $monthLabel }}</h2>
            <button type="button" wire:click="nextMonth" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50">&rarr;</button>
            <button type="button" wire:click="goToToday" class="ml-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Today</button>
        </div>

        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            @foreach ([
                'meeting' => ['label' => 'Meetings', 'on' => 'bg-indigo-100 text-indigo-700', 'off' => 'bg-gray-50 text-gray-400'],
                'task' => ['label' => 'Tasks', 'on' => 'bg-amber-100 text-amber-700', 'off' => 'bg-gray-50 text-gray-400'],
                'project' => ['label' => 'Project deadlines', 'on' => 'bg-purple-100 text-purple-700', 'off' => 'bg-gray-50 text-gray-400'],
                'leave' => ['label' => 'Leave', 'on' => 'bg-emerald-100 text-emerald-700', 'off' => 'bg-gray-50 text-gray-400'],
            ] as $type => $style)
                <button
                    type="button"
                    wire:click="toggleType('{{ $type }}')"
                    @class([
                        'rounded-full px-2.5 py-1 font-medium border',
                        $style['on'].' border-transparent' => in_array($type, $activeTypes),
                        $style['off'].' border-gray-200' => ! in_array($type, $activeTypes),
                    ])
                >{{ $style['label'] }}</button>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        <div class="grid grid-cols-7 border-b border-gray-100 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $label)
                <div class="py-2">{{ $label }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7">
            @foreach ($days as $day)
                <div @class([
                    'min-h-[7rem] border-b border-r border-gray-100 p-1.5 align-top',
                    'bg-gray-50 text-gray-400' => ! $day['inMonth'],
                ])>
                    <p @class([
                        'text-xs font-medium',
                        'text-gray-400' => ! $day['inMonth'],
                        'text-gray-700' => $day['inMonth'] && ! $day['isToday'],
                        'inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-white' => $day['isToday'],
                    ])>{{ $day['day'] }}</p>

                    <div class="mt-1 space-y-0.5">
                        @foreach ($day['events']->take(3) as $event)
                            @php($colors = [
                                'meeting' => 'bg-indigo-100 text-indigo-700',
                                'task' => 'bg-amber-100 text-amber-700',
                                'project' => 'bg-purple-100 text-purple-700',
                                'leave' => 'bg-emerald-100 text-emerald-700',
                            ])
                            @if ($event['url'])
                                <a href="{{ $event['url'] }}" class="block truncate rounded px-1 py-0.5 text-[11px] leading-tight {{ $colors[$event['type']] }}" title="{{ $event['title'] }} — {{ $event['subtitle'] }}">
                                    {{ $event['time'] ? $event['time'].' ' : '' }}{{ $event['title'] }}
                                </a>
                            @else
                                <p class="block truncate rounded px-1 py-0.5 text-[11px] leading-tight {{ $colors[$event['type']] }}" title="{{ $event['title'] }} — {{ $event['subtitle'] }}">
                                    {{ $event['title'] }}
                                </p>
                            @endif
                        @endforeach
                        @if ($day['events']->count() > 3)
                            <p class="px-1 text-[11px] text-gray-400">+{{ $day['events']->count() - 3 }} more</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
