<x-app-layout>
    <x-slot name="header">Project Updates</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" class="flex items-center gap-2">
                    @if ($activeGroup)
                        <input type="hidden" name="group" value="{{ $activeGroup }}" />
                    @endif
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
                </form>

                {{-- Group toggle links --}}
                <div class="flex items-center gap-1 text-sm">
                    <a href="{{ route('projects.index', array_filter(['status' => $filters['status'] ?? null])) }}"
                       class="px-2 py-1 rounded @if(!$activeGroup) bg-indigo-600 text-white @else text-gray-500 hover:bg-gray-100 @endif">All</a>
                    <a href="{{ route('projects.index', array_filter(['group' => 'client', 'status' => $filters['status'] ?? null])) }}"
                       class="px-2 py-1 rounded @if($activeGroup === 'client') bg-indigo-600 text-white @else text-gray-500 hover:bg-gray-100 @endif">Client-wise</a>
                    <a href="{{ route('projects.index', array_filter(['group' => 'owner', 'status' => $filters['status'] ?? null])) }}"
                       class="px-2 py-1 rounded @if($activeGroup === 'owner') bg-indigo-600 text-white @else text-gray-500 hover:bg-gray-100 @endif">Employee-wise</a>
                    <a href="{{ route('projects.index', array_filter(['group' => 'service', 'status' => $filters['status'] ?? null])) }}"
                       class="px-2 py-1 rounded @if($activeGroup === 'service') bg-indigo-600 text-white @else text-gray-500 hover:bg-gray-100 @endif">Service-wise</a>
                </div>
            </div>
            @can('create', \App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">New Project</a>
            @endcan
        </div>

        @if ($grouped)
            {{-- Grouped view --}}
            @forelse ($grouped as $groupName => $groupProjects)
                <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-2">
                        <h3 class="text-sm font-semibold text-gray-700">{{ $groupLabel }}: {{ $groupName }} <span class="text-gray-400">({{ $groupProjects->count() }})</span></h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Project</th>
                                @if ($activeGroup !== 'client') <th class="px-4 py-3">Client</th> @endif
                                @if ($activeGroup !== 'owner') <th class="px-4 py-3">Owner</th> @endif
                                @if ($activeGroup !== 'service') <th class="px-4 py-3">Service</th> @endif
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Tasks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($groupProjects as $project)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><a href="{{ route('projects.show', $project) }}" class="font-medium text-indigo-600 hover:underline">{{ $project->name }}</a></td>
                                    @if ($activeGroup !== 'client') <td class="px-4 py-3 text-gray-600">{{ $project->customer?->company_name ?? 'Client removed' }}</td> @endif
                                    @if ($activeGroup !== 'owner') <td class="px-4 py-3 text-gray-600">{{ $project->owner?->name ?? '—' }}</td> @endif
                                    @if ($activeGroup !== 'service') <td class="px-4 py-3 text-gray-600">{{ $project->service?->name ?? '—' }}</td> @endif
                                    <td class="px-4 py-3 text-gray-600">{{ $project->status->label() }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ $project->tasks_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="rounded-lg bg-white p-10 text-center text-gray-400 shadow-sm">No projects found.</div>
            @endforelse
        @else
            {{-- Flat paginated view --}}
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Project</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Tasks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($projects as $project)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3"><a href="{{ route('projects.show', $project) }}" class="font-medium text-indigo-600 hover:underline">{{ $project->name }}</a></td>
                                <td class="px-4 py-3 text-gray-600">{{ $project->customer?->company_name ?? 'Client removed' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $project->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $project->status->label() }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ $project->tasks_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No projects yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $projects->links() }}</div>
        @endif
    </div>
</x-app-layout>
