<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Pending tasks</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['pendingTasks']) }}</p>
        <div class="mt-3">
            <a href="{{ route('tasks.index') }}" class="text-sm text-indigo-600 hover:underline">View tasks →</a>
        </div>
    </div>
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Completed today</p>
        <p class="mt-2 text-3xl font-semibold text-green-600">{{ number_format($stats['completedToday']) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Active projects</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['projects']) }}</p>
        <div class="mt-3">
            <a href="{{ route('projects.index') }}" class="text-sm text-indigo-600 hover:underline">View projects →</a>
        </div>
    </div>
</div>

<livewire:my-productivity />
