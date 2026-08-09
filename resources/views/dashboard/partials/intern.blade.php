@php($anyStatCard = collect(['pending_tasks', 'completed_today', 'active_projects'])->contains(fn ($k) => in_array($k, $visibleWidgets)))

@if ($anyStatCard)
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @if (in_array('pending_tasks', $visibleWidgets))
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Pending tasks</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['pendingTasks']) }}</p>
                <div class="mt-3">
                    <a href="{{ route('tasks.index', ['mine' => 1, 'type' => 'all', 'pending' => 1]) }}" class="text-sm text-indigo-600 hover:underline">View tasks →</a>
                </div>
            </div>
        @endif
        @if (in_array('completed_today', $visibleWidgets))
            <a href="{{ route('tasks.index', ['mine' => 1, 'type' => 'all', 'completed_today' => 1]) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm text-gray-500">Completed today</p>
                <p class="mt-2 text-3xl font-semibold text-green-600">{{ number_format($stats['completedToday']) }}</p>
            </a>
        @endif
        @if (in_array('active_projects', $visibleWidgets))
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Active projects</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['projects']) }}</p>
                <div class="mt-3">
                    <a href="{{ route('projects.index') }}" class="text-sm text-indigo-600 hover:underline">View projects →</a>
                </div>
            </div>
        @endif
    </div>
@endif

@if (in_array('my_productivity', $visibleWidgets))
    <livewire:my-productivity />
@endif
