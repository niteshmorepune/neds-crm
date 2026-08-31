@php
    // Grouped by service (outer sortBy), then chronological within each
    // service (inner sortBy) — PHP's sort is stable, so the start_date order
    // survives the following service-name sort instead of being scrambled.
    // Orphaned templates (billing was attempted then the invoice was
    // deleted, never reactivated) are excluded — they'd otherwise show as a
    // misleading "On Hold" or "Ended" row for something that was retracted,
    // not a real ongoing or concluded service. See RecurringInvoice::isOrphaned().
    // $reselleredRecurring: templates billed to a reseller's own Customer
    // record but attributed back to this client via project_id — see
    // CustomerController::show(). Merged here for visibility only, tagged
    // "Billed via X" below; the top-of-page recurring-value/renewal tiles
    // deliberately do NOT include these (they mean "billed directly to this
    // client").
    $recurring  = $client->nonOrphanedRecurringInvoices()
        ->concat($reselleredRecurring ?? [])
        ->sortBy(fn ($r) => $r->start_date)
        ->sortBy(fn ($r) => $r->service?->name);
    $projects   = $client->projects->sortBy(fn ($p) => $p->service?->name);
    // Derived from the same dashboardStatus() the row badges use below, so
    // the summary counts can never drift out of sync with what's displayed
    // per row (the exact bug that made "On hold" over-count Ended templates).
    $statusCounts = $recurring->map(fn ($r) => $r->dashboardStatus($canViewInvoices))->countBy();
    $activeCount = $statusCounts['active'] ?? 0;
    $onHoldCount = $statusCounts['on_hold'] ?? 0;

    $nextBill = $recurring->where('is_active', true)
        ->min('next_run_on');

    // Team column for Recurring Services: a live (non-Completed) Project for
    // the same service always wins (its own owner/assignees, shown exactly
    // like the Projects table below) — the new per-service assignment only
    // fills in when no such Project exists, so a service never shows two
    // competing "who's working on this" answers. See CustomerPolicy::
    // manageServices() / ServiceAssignmentController.
    $liveProjectsByService = $client->projects
        ->reject(fn ($p) => $p->status === \App\Enums\ProjectStatus::Completed)
        ->groupBy('service_id');
    $assignmentsByService = $client->serviceAssignments->keyBy('service_id');
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
                    <th class="px-4 py-2">Team</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @php
                    $statusStyles = [
                        'upcoming' => ['label' => 'Upcoming', 'classes' => 'bg-indigo-50 text-indigo-700'],
                        'active' => ['label' => 'Active', 'classes' => 'bg-emerald-50 text-emerald-700'],
                        'on_hold' => ['label' => 'On Hold', 'classes' => 'bg-amber-50 text-amber-700'],
                        'payment_received' => ['label' => 'Payment Received', 'classes' => 'bg-emerald-50 text-emerald-700'],
                        'payment_pending' => ['label' => 'Payment Pending', 'classes' => 'bg-amber-50 text-amber-700'],
                        'not_billed' => ['label' => 'Not Billed', 'classes' => 'bg-gray-50 text-gray-500'],
                        'ended' => ['label' => 'Ended', 'classes' => 'bg-gray-100 text-gray-600'],
                    ];
                @endphp
                @foreach ($recurring as $r)
                    @php
                        $cycleAmount = $r->items->sum(
                            fn ($item) => (int) round((float) $item->quantity * (int) $item->rate)
                        );
                        $status = $statusStyles[$r->dashboardStatus($canViewInvoices)];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $r->service?->name ?? '—' }}
                            @if ($r->customer_id !== $client->id)
                                <span class="block text-xs font-normal text-amber-700">Billed via {{ $r->customer?->company_name ?? 'Client removed' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $r->start_date?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $r->end_date?->format('d M Y') ?? 'Ongoing' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $status['classes'] }}">{{ $status['label'] }}</span>
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
                        <td class="px-4 py-3">
                            @php
                                $liveProjects = $liveProjectsByService->get($r->service_id);
                            @endphp
                            @if ($liveProjects && $liveProjects->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($liveProjects as $liveProject)
                                        @if ($liveProject->owner)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                                {{ $liveProject->owner->name }}
                                                <span class="rounded bg-indigo-200 px-1 text-indigo-800">Lead</span>
                                            </span>
                                        @endif
                                        @foreach ($liveProject->assignees as $member)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                {{ $member->name }}
                                                <span class="rounded bg-gray-200 px-1 text-gray-600">{{ ucfirst($member->pivot->role) }}</span>
                                            </span>
                                        @endforeach
                                    @endforeach
                                </div>
                            @else
                                @php
                                    $assignment = $assignmentsByService->get($r->service_id);
                                @endphp
                                <div x-data="{ editing: false }">
                                    <div class="flex items-center gap-2" x-show="!editing">
                                        <span class="text-gray-600">{{ $assignment?->user?->name ?? '—' }}</span>
                                        @if ($canManageServices)
                                            <button type="button" @click="editing = true" class="text-xs text-indigo-600 hover:underline">{{ $assignment ? 'Change' : 'Assign' }}</button>
                                            @if ($assignment)
                                                <form method="POST" action="{{ route('service-assignments.destroy', $assignment) }}" class="inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-xs text-red-500 hover:text-red-600">Remove</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                    @if ($canManageServices)
                                        <form method="POST" action="{{ route('service-assignments.store', $client) }}" x-show="editing" x-cloak class="mt-1 flex items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="service_id" value="{{ $r->service_id }}">
                                            <select name="user_id" class="rounded-md border-gray-300 text-xs shadow-sm" required>
                                                <option value="">Select</option>
                                                @foreach ($staff as $person)
                                                    <option value="{{ $person->id }}" @selected($assignment?->user_id === $person->id)>{{ $person->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="text-xs text-indigo-600 hover:underline">Save</button>
                                            <button type="button" @click="editing = false" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </td>
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

<div class="mt-8 border-t border-gray-100 pt-6">
    <livewire:client-service-links :customer="$client" :can-manage="$canManageServices" />
</div>
