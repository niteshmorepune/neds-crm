<x-portal-app-layout header="My Projects">
    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr><th class="px-4 py-3">Project</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Timeline</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($projects as $project)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><a href="{{ route('portal.projects.show', $project->id) }}" class="font-medium text-indigo-600 hover:underline">{{ $project->name }}</a></td>
                        <td class="px-4 py-3 text-gray-600">{{ $project->status->label() }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $project->start_date?->format('d M Y') ?? '—' }} → {{ $project->end_date?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No projects yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $projects->links() }}</div>
</x-portal-app-layout>
