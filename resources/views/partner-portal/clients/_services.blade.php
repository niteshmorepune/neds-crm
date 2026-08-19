@php
    // Same grouping/sort and status-derivation as the internal client page's
    // Services tab (clients/_services_tab.blade.php) — reused here so a
    // recurring service's status (including On Hold) reads identically
    // everywhere in the app. Deliberately omits ₹ amounts and the exact
    // next-bill date (operational billing detail already covered by the
    // Account statement above) and the internal project link/Team column
    // (staff-only routes/assignment detail, not a partner's business).
    $recurring = $customer->nonOrphanedRecurringInvoices()
        ->sortBy(fn ($r) => $r->start_date)
        ->sortBy(fn ($r) => $r->service?->name);
    $projects = $customer->projects->sortBy(fn ($p) => $p->service?->name);

    // dashboardStatus(false) — same "no invoice-level detail" path the
    // internal component uses for a viewer without invoice access. A
    // partner doesn't see per-service ₹ amounts here (the Account
    // statement above already covers real invoice/payment detail), so a
    // concluded period reads as a plain "Ended" rather than exposing
    // whether that specific cycle was paid.
    $statusStyles = [
        'upcoming' => ['label' => 'Upcoming', 'classes' => 'bg-indigo-50 text-indigo-700'],
        'active' => ['label' => 'Active', 'classes' => 'bg-emerald-50 text-emerald-700'],
        'on_hold' => ['label' => 'On Hold', 'classes' => 'bg-amber-50 text-amber-700'],
        'not_billed' => ['label' => 'Not Billed', 'classes' => 'bg-gray-50 text-gray-500'],
        'ended' => ['label' => 'Ended', 'classes' => 'bg-gray-100 text-gray-600'],
    ];
@endphp

<div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
    <h3 class="text-base font-semibold text-gray-900">Recurring Services</h3>

    @if ($recurring->isEmpty())
        <p class="mt-4 text-sm text-gray-400">No recurring services set up for this client.</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">Service</th>
                        <th class="py-2">Started</th>
                        <th class="py-2">End date</th>
                        <th class="py-2">Status</th>
                        <th class="py-2">Frequency</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($recurring as $r)
                        @php $status = $statusStyles[$r->dashboardStatus(false)]; @endphp
                        <tr>
                            <td class="py-2 font-medium text-gray-900">{{ $r->service?->name ?? '—' }}</td>
                            <td class="py-2 text-gray-600">{{ $r->start_date?->format('d M Y') ?? '—' }}</td>
                            <td class="py-2 text-gray-600">{{ $r->end_date?->format('d M Y') ?? 'Ongoing' }}</td>
                            <td class="py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $status['classes'] }}">{{ $status['label'] }}</span>
                            </td>
                            <td class="py-2 text-gray-600">{{ $r->frequency->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
    <h3 class="text-base font-semibold text-gray-900">Projects</h3>

    @if ($projects->isEmpty())
        <p class="mt-4 text-sm text-gray-400">No projects for this client.</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">Project</th>
                        <th class="py-2">Service</th>
                        <th class="py-2">Started</th>
                        <th class="py-2">End date</th>
                        <th class="py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($projects as $project)
                        @php
                            $overdue = $project->status === \App\Enums\ProjectStatus::Active
                                && $project->end_date?->isPast();
                            $badge = match (true) {
                                $overdue => ['bg-red-50 text-red-700', 'Overdue'],
                                $project->status === \App\Enums\ProjectStatus::Active => ['bg-emerald-50 text-emerald-700', 'Active'],
                                $project->status === \App\Enums\ProjectStatus::OnHold => ['bg-amber-50 text-amber-700', 'On Hold'],
                                $project->status === \App\Enums\ProjectStatus::Completed => ['bg-gray-100 text-gray-600', 'Completed'],
                                default => ['bg-gray-100 text-gray-600', $project->status->label()],
                            };
                        @endphp
                        <tr>
                            <td class="py-2 font-medium text-gray-900">{{ $project->name }}</td>
                            <td class="py-2 text-gray-600">{{ $project->service?->name ?? '—' }}</td>
                            <td class="py-2 text-gray-600">{{ $project->start_date?->format('d M Y') ?? '—' }}</td>
                            <td class="py-2 text-gray-600">{{ $project->end_date?->format('d M Y') ?? '—' }}</td>
                            <td class="py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge[0] }}">{{ $badge[1] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
