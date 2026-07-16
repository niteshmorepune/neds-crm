@php
    $recurring  = $client->recurringInvoices->sortBy(fn ($r) => $r->service?->name);
    $projects   = $client->projects->sortBy(fn ($p) => $p->service?->name);
    $activeCount = $recurring->where('is_active', true)->count();
    $onHoldCount = $recurring->where('is_active', false)->count();

    $nextBill = $recurring->where('is_active', true)
        ->min('next_run_on');
@endphp

{{-- Summary strip --}}
@if ($recurring->isNotEmpty() || $projects->isNotEmpty())
    <dl class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg bg-emerald-50 px-4 py-3">
            <dt class="text-xs font-medium text-emerald-700">Active recurring</dt>
            <dd class="mt-1 text-2xl font-semibold text-emerald-900">{{ $activeCount }}</dd>
        </div>
        <div class="rounded-lg bg-amber-50 px-4 py-3">
            <dt class="text-xs font-medium text-amber-700">On hold</dt>
            <dd class="mt-1 text-2xl font-semibold text-amber-900">{{ $onHoldCount }}</dd>
        </div>
        <div class="rounded-lg bg-indigo-50 px-4 py-3">
            <dt class="text-xs font-medium text-indigo-700">Projects</dt>
            <dd class="mt-1 text-2xl font-semibold text-indigo-900">{{ $projects->count() }}</dd>
        </div>
        <div class="rounded-lg bg-gray-50 px-4 py-3">
            <dt class="text-xs font-medium text-gray-500">Next billing</dt>
            <dd class="mt-1 text-sm font-semibold text-gray-900">
                {{ $nextBill ? $nextBill->timezone(config('app.display_timezone'))->format('d M Y') : '—' }}
            </dd>
        </div>
    </dl>
@endif

{{-- Recurring services --}}
<h3 class="mb-3 text-sm font-semibold text-gray-700">Recurring Services</h3>
@if ($recurring->isEmpty())
    <p class="mb-6 text-sm text-gray-400">No recurring services set up for this client.</p>
@else
    <div class="mb-8 overflow-x-auto rounded-lg border border-gray-100">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-2">Service</th>
                    <th class="px-4 py-2">Started</th>
                    <th class="px-4 py-2">End date</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Frequency</th>
                    @if ($canViewInvoices)
                        <th class="px-4 py-2 text-right">Est. / cycle</th>
                        <th class="px-4 py-2">Next bill</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($recurring as $r)
                    @php
                        $cycleAmount = $r->items->sum(
                            fn ($item) => (int) round((float) $item->quantity * (int) $item->rate)
                        );
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $r->service?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $r->start_date?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $r->end_date?->format('d M Y') ?? 'Ongoing' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($r->is_active)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">On Hold</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $r->frequency->label() }}</td>
                        @if ($canViewInvoices)
                            <td class="px-4 py-3 text-right text-gray-600">
                                {{ $cycleAmount ? \App\Support\Money::format($cycleAmount) : '—' }}
                                @unless ($r->is_gst_exempt)
                                    <span class="text-xs text-gray-400">+GST</span>
                                @endunless
                            </td>
                            <td class="px-4 py-3">
                                @if ($r->is_active && $r->next_run_on)
                                    <span @class([
                                        'font-medium',
                                        'text-red-600' => $r->next_run_on->isPast(),
                                        'text-gray-900' => ! $r->next_run_on->isPast(),
                                    ])>
                                        {{ $r->next_run_on->format('d M Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Projects --}}
<h3 class="mb-3 text-sm font-semibold text-gray-700">Projects</h3>
@if ($projects->isEmpty())
    <p class="text-sm text-gray-400">No projects for this client.</p>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-100">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-2">Project</th>
                    <th class="px-4 py-2">Service</th>
                    <th class="px-4 py-2">Started</th>
                    <th class="px-4 py-2">End date</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Team</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($projects as $project)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('projects.show', $project) }}" class="font-medium text-indigo-600 hover:underline">
                                {{ $project->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $project->service?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $project->start_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $project->end_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $status   = $project->status;
                                $overdue  = $status === \App\Enums\ProjectStatus::Active
                                    && $project->end_date
                                    && $project->end_date->isPast();
                            @endphp
                            @if ($overdue)
                                <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Overdue</span>
                            @else
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-700' => $status === \App\Enums\ProjectStatus::Active,
                                    'bg-amber-50 text-amber-700'    => $status === \App\Enums\ProjectStatus::OnHold,
                                    'bg-gray-100 text-gray-600'     => $status === \App\Enums\ProjectStatus::Completed,
                                ])>{{ $status->label() }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                @if ($project->owner)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                        {{ $project->owner->name }}
                                        <span class="rounded bg-indigo-200 px-1 text-indigo-800">Lead</span>
                                    </span>
                                @endif
                                @foreach ($project->assignees as $member)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                        {{ $member->name }}
                                        <span class="rounded bg-gray-200 px-1 text-gray-600">{{ ucfirst($member->pivot->role) }}</span>
                                    </span>
                                @endforeach
                                @if (! $project->owner && $project->assignees->isEmpty())
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
