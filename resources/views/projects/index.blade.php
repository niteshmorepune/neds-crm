<x-app-layout>
    <x-slot name="header">Project Updates</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <form method="GET" class="flex items-center gap-2">
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            </form>
            @can('create', \App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">New Project</a>
            @endcan
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
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
                            <td class="px-4 py-3 text-gray-600">{{ $project->customer->company_name }}</td>
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
    </div>
</x-app-layout>
