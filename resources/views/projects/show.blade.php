<x-app-layout>
    <x-slot name="header">{{ $project->name }}</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $project->name }}</h1>
                    <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                        <div><span class="text-gray-400">Client:</span> <a href="{{ route('clients.show', $project->customer) }}" class="text-indigo-600 hover:underline">{{ $project->customer->company_name }}</a></div>
                        <div><span class="text-gray-400">Status:</span> {{ $project->status->label() }}</div>
                        <div><span class="text-gray-400">Owner:</span> {{ $project->owner?->name ?? '—' }}</div>
                        <div><span class="text-gray-400">Service:</span> {{ $project->service?->name ?? '—' }}</div>
                        <div><span class="text-gray-400">Timeline:</span> {{ $project->start_date?->format('d M Y') ?? '—' }} → {{ $project->end_date?->format('d M Y') ?? '—' }}</div>
                        @if ($project->deal)<div><span class="text-gray-400">Deal:</span> <a href="{{ route('deals.show', $project->deal) }}" class="text-indigo-600 hover:underline">{{ $project->deal->title }}</a></div>@endif
                    </dl>
                    @if ($project->assignees->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($project->assignees as $a)
                                <span class="inline-flex rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $a->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
                    @can('update', $project)
                        <a href="{{ route('projects.edit', $project) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Edit</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Tasks</h2>
                <a href="{{ route('tasks.create', ['project_id' => $project->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">+ Add task</a>
            </div>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($tasks as $task)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('tasks.show', $task) }}" class="text-indigo-600 hover:underline">{{ $task->title }}</a>
                        <span class="text-gray-500">{{ $task->status->label() }} · {{ $task->assignee?->name ?? 'Unassigned' }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-400">No tasks yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
