<x-portal-app-layout :header="$project->name">
    {{-- Project details card --}}
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-600">
                <div><span class="font-medium text-gray-400">Status</span>
                    <span @class([
                        'ml-2 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                        'bg-blue-100 text-blue-700'   => $project->status->value === 'active',
                        'bg-green-100 text-green-800' => $project->status->value === 'completed',
                        'bg-gray-100 text-gray-600'   => !in_array($project->status->value, ['active', 'completed']),
                    ])>{{ $project->status->label() }}</span>
                </div>
                @if ($project->service)
                    <div><span class="font-medium text-gray-400">Service</span><span class="ml-2">{{ $project->service->name }}</span></div>
                @endif
                @if ($project->start_date || $project->end_date)
                    <div>
                        <span class="font-medium text-gray-400">Timeline</span>
                        <span class="ml-2">
                            {{ $project->start_date?->format('d M Y') ?? '—' }}
                            @if ($project->end_date) → {{ $project->end_date->format('d M Y') }} @endif
                        </span>
                    </div>
                @endif
            </dl>
        </div>

        @if ($project->description)
            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $project->description }}</p>
            </div>
        @endif
    </div>

    {{-- Employee updates / notes --}}
    <div class="mt-6">
        <h2 class="mb-3 text-base font-semibold text-gray-900">Updates from Team</h2>

        @forelse ($project->notes as $note)
            <div class="mb-3 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center justify-between text-xs text-gray-400 mb-2">
                    <span class="font-medium text-gray-600">{{ $note->author?->name ?? 'Team' }}</span>
                    <span>{{ $note->created_at->setTimezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
                </div>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $note->body }}</p>
            </div>
        @empty
            <div class="rounded-xl bg-white px-5 py-8 text-center shadow-sm ring-1 ring-gray-100">
                <p class="text-sm text-gray-400">No updates posted yet. We'll keep you informed here as work progresses.</p>
            </div>
        @endforelse
    </div>

    {{-- Raise ticket callout --}}
    <div class="mt-6 flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4">
        <svg class="mt-0.5 shrink-0 text-indigo-500" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-indigo-800">
            Have a question or concern about this project?
            <a href="{{ route('portal.tickets.create') }}" class="font-semibold underline hover:text-indigo-600">Raise a ticket</a>
            and our team will respond promptly.
        </p>
    </div>

    <div class="mt-4">
        <a href="{{ route('portal.projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to projects</a>
    </div>
</x-portal-app-layout>
