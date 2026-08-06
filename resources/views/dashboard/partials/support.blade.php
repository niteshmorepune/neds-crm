<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <a href="{{ route('tickets.index', ['mine' => 1, 'open' => 1]) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
        <p class="text-sm text-gray-500">Open tickets</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['open_total']) }}</p>
    </a>
    <a href="{{ route('tickets.index', ['mine' => 1, 'at_risk' => 1]) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
        <p class="text-sm text-gray-500">SLA at risk (next 4h or breached)</p>
        <p class="mt-2 text-3xl font-semibold text-red-600">{{ number_format($stats['sla_at_risk']) }}</p>
    </a>
    <a href="{{ route('tasks.index', ['mine' => 1, 'type' => 'all']) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
        <p class="text-sm text-gray-500">Total tasks</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['tasks_total']) }}</p>
    </a>
    <a href="{{ route('tasks.index', ['mine' => 1, 'type' => 'all', 'pending' => 1]) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
        <p class="text-sm text-gray-500">Pending tasks</p>
        <p class="mt-2 text-3xl font-semibold text-amber-600">{{ number_format($stats['tasks_pending']) }}</p>
    </a>
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Overdue tasks</p>
        <p class="mt-2 text-3xl font-semibold text-red-600">{{ number_format($stats['tasks_overdue']) }}</p>
        <div class="mt-3">
            <a href="{{ route('tasks.index', ['mine' => 1, 'type' => 'all', 'overdue' => 1]) }}" class="text-sm text-indigo-600 hover:underline">View my tasks →</a>
        </div>
    </div>
</div>

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h3 class="text-base font-semibold text-gray-900">Open tickets by priority</h3>
    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ($stats['open_by_priority'] as $label => $count)
            <a href="{{ route('tickets.index', ['mine' => 1, 'open' => 1, 'priority' => \App\Enums\TicketPriority::from(strtolower($label))->value]) }}" class="block hover:opacity-75">
                <p class="text-2xl font-semibold text-gray-900">{{ $count }}</p>
                <p class="text-xs text-gray-500">{{ $label }}</p>
            </a>
        @endforeach
    </div>
    <div class="mt-5">
        <a href="{{ route('tickets.index', ['mine' => 1, 'open' => 1]) }}" class="text-sm text-indigo-600 hover:underline">Go to tickets →</a>
    </div>
</div>

<livewire:my-productivity />
