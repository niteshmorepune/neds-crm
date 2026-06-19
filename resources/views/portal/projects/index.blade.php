<x-portal-app-layout header="My Projects">
    {{-- Ticket callout --}}
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4">
        <svg class="mt-0.5 shrink-0 text-indigo-500" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-indigo-800">
            For any discussion or queries regarding your projects, please
            <a href="{{ route('portal.tickets.create') }}" class="font-semibold underline hover:text-indigo-600">raise a ticket</a>
            and our team will get back to you promptly.
        </p>
    </div>

    @if ($projects->isEmpty())
        <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-100">
            <p class="text-gray-400 text-sm">No projects yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($projects as $project)
                <a href="{{ route('portal.projects.show', $project->id) }}"
                   class="flex items-center justify-between rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow transition-all group">
                    <div>
                        <div class="font-medium text-gray-900 group-hover:text-indigo-700">{{ $project->name }}</div>
                        <div class="mt-0.5 text-xs text-gray-400">
                            {{ $project->start_date?->format('d M Y') ?? '—' }}
                            @if ($project->end_date) → {{ $project->end_date->format('d M Y') }} @endif
                        </div>
                    </div>
                    <span @class([
                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium shrink-0',
                        'bg-blue-100 text-blue-700'   => $project->status->value === 'active',
                        'bg-green-100 text-green-800' => $project->status->value === 'completed',
                        'bg-gray-100 text-gray-600'   => !in_array($project->status->value, ['active', 'completed']),
                    ])>{{ $project->status->label() }}</span>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $projects->links() }}</div>
    @endif
</x-portal-app-layout>
