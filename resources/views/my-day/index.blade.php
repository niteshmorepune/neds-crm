<x-app-layout>
    <x-slot name="header">My Day</x-slot>

    <div class="max-w-3xl mx-auto space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900">
                My Day
                @if ($items->isNotEmpty())
                    <span class="ml-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ $items->count() }}</span>
                @endif
            </h1>
        </div>

        @php
            $typeMeta = [
                'task' => ['label' => 'Task', 'classes' => 'bg-slate-100 text-slate-700'],
                'lead' => ['label' => 'Lead', 'classes' => 'bg-blue-100 text-blue-700'],
                'deal' => ['label' => 'Deal', 'classes' => 'bg-purple-100 text-purple-700'],
                'call' => ['label' => 'Call', 'classes' => 'bg-amber-100 text-amber-700'],
                'ticket' => ['label' => 'Ticket', 'classes' => 'bg-red-100 text-red-700'],
            ];
        @endphp

        <div class="rounded-lg bg-white shadow-sm">
            @if ($items->isEmpty())
                <p class="p-8 text-center text-sm text-gray-400">Nothing due right now. 🎉</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($items as $item)
                        <li class="flex items-center justify-between gap-4 p-4">
                            <div class="min-w-0">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $typeMeta[$item['type']]['classes'] }}">
                                    {{ $typeMeta[$item['type']]['label'] }}
                                </span>
                                <a href="{{ $item['url'] }}" class="ml-2 text-sm font-medium text-indigo-600 hover:underline">{{ $item['title'] }}</a>
                                @if ($item['subtitle'])
                                    <p class="mt-0.5 truncate text-xs text-gray-500">{{ $item['subtitle'] }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 text-xs text-red-600">
                                due {{ $item['when']->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
