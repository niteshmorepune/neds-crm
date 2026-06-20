<x-portal-app-layout header="Your Services">

    @if ($projects->isEmpty() && $recurring->isEmpty())
        <div class="rounded-xl bg-white p-12 text-center shadow-sm ring-1 ring-gray-100">
            <svg class="mx-auto mb-3 w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <p class="text-sm text-gray-400">No active services yet.</p>
        </div>
    @endif

    {{-- Active Projects / One-time Services --}}
    @if ($projects->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Active Services</h2>
            <div class="space-y-3">
                @foreach ($projects as $project)
                    @php
                        $handler = $project->assignees->first() ?? $project->owner;
                        $extraCount = $project->assignees->count() - 1;
                        $overdue = $project->status === \App\Enums\ProjectStatus::Active
                            && $project->end_date?->isPast();
                        $badge = match(true) {
                            $overdue => ['bg-red-50 text-red-700', 'Overdue'],
                            $project->status === \App\Enums\ProjectStatus::Active    => ['bg-emerald-50 text-emerald-700', 'Active'],
                            $project->status === \App\Enums\ProjectStatus::OnHold    => ['bg-amber-50 text-amber-700', 'On Hold'],
                            $project->status === \App\Enums\ProjectStatus::Completed => ['bg-gray-100 text-gray-600', 'Completed'],
                            default => ['bg-gray-100 text-gray-600', $project->status->label()],
                        };
                    @endphp
                    <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-gray-900">{{ $project->service?->name ?? $project->name }}</span>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge[0] }}">{{ $badge[1] }}</span>
                                </div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ $project->name }}</div>
                            </div>

                            @if ($handler)
                                <div class="flex items-center gap-2 shrink-0">
                                    @php
                                        $initials = collect(explode(' ', $handler->name))->map(fn($p) => strtoupper(substr($p,0,1)))->take(2)->join('');
                                    @endphp
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700 shrink-0">{{ $initials }}</div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $handler->name }}
                                            @if ($extraCount > 0)
                                                <span class="text-xs text-gray-400">+{{ $extraCount }} more</span>
                                            @endif
                                        </div>
                                        @if ($handler->email)
                                            <a href="mailto:{{ $handler->email }}" class="text-xs text-indigo-600 hover:underline">{{ $handler->email }}</a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($project->start_date || $project->end_date)
                            <div class="mt-2 text-xs text-gray-400">
                                {{ $project->start_date?->format('d M Y') ?? '—' }}
                                @if ($project->end_date) → {{ $project->end_date->format('d M Y') }} @endif
                            </div>
                        @endif

                        <div class="mt-2">
                            <a href="{{ route('portal.projects.show', $project->id) }}" class="text-xs font-medium text-indigo-600 hover:underline">View updates →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recurring Retainer Services --}}
    @if ($recurring->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Retainer Services</h2>
            <div class="space-y-3">
                @foreach ($recurring as $r)
                    <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">{{ $r->service?->name ?? 'Service' }}</span>
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                    <span class="text-xs text-gray-400">{{ $r->frequency->label() }}</span>
                                </div>
                                @if ($r->start_date)
                                    <div class="mt-0.5 text-xs text-gray-500">Since {{ $r->start_date->format('d M Y') }}</div>
                                @endif
                            </div>

                            @if ($accountOwner)
                                <div class="flex items-center gap-2 shrink-0">
                                    @php
                                        $initials = collect(explode(' ', $accountOwner->name))->map(fn($p) => strtoupper(substr($p,0,1)))->take(2)->join('');
                                    @endphp
                                    <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0">{{ $initials }}</div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $accountOwner->name }}</div>
                                        <div class="text-xs text-gray-400">Account Manager</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Raise a ticket callout --}}
    <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4">
        <svg class="mt-0.5 shrink-0 text-indigo-500" style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-indigo-800">
            Have a question about a service?
            <a href="{{ route('portal.tickets.create') }}" class="font-semibold underline hover:text-indigo-600">Raise a ticket</a>
            and the right team member will get back to you.
        </p>
    </div>

</x-portal-app-layout>
