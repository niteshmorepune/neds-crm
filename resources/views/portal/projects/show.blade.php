<x-portal-app-layout :header="$project->name">
    {{-- Project detail card --}}
    <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-100 mb-5">
        <div class="flex flex-wrap items-center gap-2 mb-4">
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
            @if ($project->service)
                <span class="text-xs text-gray-400">{{ $project->service->name }}</span>
            @endif
        </div>

        <dl class="grid grid-cols-1 gap-y-2 gap-x-6 text-sm text-gray-600 sm:grid-cols-2">
            @if ($project->start_date || $project->end_date)
                <div class="flex gap-2">
                    <dt class="text-gray-400 shrink-0">Timeline</dt>
                    <dd>{{ $project->start_date?->format('d M Y') ?? '—' }}
                        @if ($project->end_date) → {{ $project->end_date->format('d M Y') }} @endif
                    </dd>
                </div>
            @endif
        </dl>

        @if ($project->description)
            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $project->description }}</p>
            </div>
        @endif
    </div>

    {{-- Your NEDS Team --}}
    @if ($project->owner || $project->assignees->isNotEmpty())
        <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-100 mb-5">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Your NEDS Team</h2>
            <div class="flex flex-wrap gap-4">
                @if ($project->owner)
                    <div class="flex items-center gap-3">
                        @php
                            $initials = collect(explode(' ', $project->owner->name))->map(fn($p) => strtoupper(substr($p,0,1)))->take(2)->join('');
                        @endphp
                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-700 shrink-0">{{ $initials }}</div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $project->owner->name }}</div>
                            <div class="text-xs text-indigo-600 font-medium">Lead — {{ $project->service?->name ?? 'Project Manager' }}</div>
                            @if ($project->owner->email)
                                <a href="mailto:{{ $project->owner->email }}" class="text-xs text-gray-500 hover:text-indigo-600">{{ $project->owner->email }}</a>
                            @endif
                        </div>
                    </div>
                @endif
                @foreach ($project->assignees as $member)
                    <div class="flex items-center gap-3">
                        @php
                            $initials = collect(explode(' ', $member->name))->map(fn($p) => strtoupper(substr($p,0,1)))->take(2)->join('');
                        @endphp
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-sm font-bold text-gray-600 shrink-0">{{ $initials }}</div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $member->name }}</div>
                            <div class="text-xs text-gray-500 font-medium">{{ ucfirst($member->pivot->role) }}</div>
                            @if ($member->email)
                                <a href="mailto:{{ $member->email }}" class="text-xs text-gray-500 hover:text-indigo-600">{{ $member->email }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Updates from Team --}}
    <div class="mb-5">
        <h2 class="text-base font-semibold text-gray-900 mb-3">Updates from Our Team</h2>

        @forelse ($project->notes as $note)
            <div class="mb-3 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center justify-between text-xs text-gray-400 mb-2">
                    <div class="flex items-center gap-2">
                        @php
                            $authorName = $note->author?->name ?? 'NEDS Team';
                            $authorInitials = collect(explode(' ', $authorName))->map(fn($p) => strtoupper(substr($p,0,1)))->take(2)->join('');
                        @endphp
                        <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">{{ $authorInitials }}</div>
                        <span class="font-medium text-gray-600">{{ $authorName }}</span>
                    </div>
                    <span>{{ $note->created_at->setTimezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
                </div>
                <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $note->body }}</p>
            </div>
        @empty
            <div class="rounded-xl bg-white px-5 py-8 text-center shadow-sm ring-1 ring-gray-100">
                <p class="text-sm text-gray-400">No updates yet. We'll post progress updates here as work moves forward.</p>
            </div>
        @endforelse
    </div>

    {{-- Raise a ticket callout --}}
    <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4 mb-5">
        <svg class="mt-0.5 shrink-0 text-indigo-500" style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-indigo-800">
            Have a question or concern about this project?
            <a href="{{ route('portal.tickets.create') }}" class="font-semibold underline hover:text-indigo-600">Raise a ticket</a>
            and our team will respond promptly.
        </p>
    </div>

    <a href="{{ route('portal.projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to projects</a>
</x-portal-app-layout>
