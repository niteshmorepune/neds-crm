<x-portal-app-layout header="Your Projects">
    {{-- Ticket callout --}}
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4">
        <svg class="mt-0.5 shrink-0 text-indigo-500" style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-indigo-800">
            Questions about a project?
            <a href="{{ route('portal.tickets.create') }}" class="font-semibold underline hover:text-indigo-600">Raise a ticket</a>
            and our team will get back to you promptly.
        </p>
    </div>

    @if ($projects->isEmpty())
        <div class="rounded-xl bg-white p-12 text-center shadow-sm ring-1 ring-gray-100">
            <svg class="mx-auto mb-3 w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
            </svg>
            <p class="text-sm text-gray-400">No projects yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($projects as $project)
                <a href="{{ route('portal.projects.show', $project->id) }}"
                   class="flex items-center justify-between rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow-md transition-all group">
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 group-hover:text-indigo-700 transition-colors truncate">{{ $project->name }}</div>
                        <div class="mt-0.5 text-xs text-gray-400 flex flex-wrap gap-x-3">
                            @if ($project->service)
                                <span>{{ $project->service->name }}</span>
                            @endif
                            @if ($project->owner)
                                <span>Managed by {{ $project->owner->name }}</span>
                            @endif
                            @if($project->start_date || $project->end_date)
                                <span>{{ $project->start_date?->format('d M Y') ?? '—' }}@if ($project->end_date) → {{ $project->end_date->format('d M Y') }}@endif</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 ml-4">
                        @php
                            $badge = match($project->status->value) {
                                'active'    => 'bg-blue-100 text-blue-700',
                                'completed' => 'bg-green-100 text-green-700',
                                'on_hold'   => 'bg-amber-100 text-amber-700',
                                default     => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                            {{ $project->status->label() }}
                        </span>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $projects->links() }}</div>
    @endif
</x-portal-app-layout>
