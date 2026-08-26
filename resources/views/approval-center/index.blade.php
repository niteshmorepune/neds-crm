<x-app-layout>
    <x-slot name="header">Approval Center</x-slot>

    <div class="max-w-5xl mx-auto space-y-6" x-data="{ rejecting: null, requestingChanges: null }">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Pending approvals</p>
            <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $totalCount }}</p>
            <p class="mt-2 text-xs text-gray-400">Everything across the CRM waiting on a manager decision, in one place — Leave Requests, Work From Home Requests, Quotations, and Project Updates.</p>
        </div>

        {{-- Leave Requests --}}
        <div class="rounded-lg bg-white shadow-sm">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">🌴 Leave Requests</h2>
                <span class="text-sm text-gray-500">{{ $leaveRequests->count() }} pending</span>
            </div>
            <div class="overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Employee</th>
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3">Dates</th>
                            <th class="px-6 py-3">Reason</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($leaveRequests as $r)
                            <tr>
                                <td class="px-6 py-3 text-gray-700">{{ $r->user?->name }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $r->type->label() }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $r->start_date->format('d M Y') }} – {{ $r->end_date->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $r->reason }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('leave-requests.approve', $r) }}">
                                            @csrf
                                            <button class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-500">Approve</button>
                                        </form>
                                        <button type="button" @click="rejecting = rejecting === 'lr-{{ $r->id }}' ? null : 'lr-{{ $r->id }}'"
                                                class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500">Reject</button>
                                    </div>
                                    <form x-cloak x-show="rejecting === 'lr-{{ $r->id }}'" method="POST" action="{{ route('leave-requests.reject', $r) }}" class="mt-2 flex items-center gap-2">
                                        @csrf
                                        <input type="text" name="review_notes" placeholder="Reason (optional)" maxlength="255" class="rounded-md border-gray-300 text-xs shadow-sm" />
                                        <button class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Confirm reject</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-gray-400">No pending leave requests.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Work From Home Requests --}}
        <div class="rounded-lg bg-white shadow-sm">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">🏠 Work From Home Requests</h2>
                <span class="text-sm text-gray-500">{{ $workFromHomeRequests->count() }} pending</span>
            </div>
            <div class="overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Employee</th>
                            <th class="px-6 py-3">Duration</th>
                            <th class="px-6 py-3">Dates</th>
                            <th class="px-6 py-3">Reason</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($workFromHomeRequests as $r)
                            <tr>
                                <td class="px-6 py-3 text-gray-700">{{ $r->user?->name }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $r->type->label() }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $r->start_date->format('d M Y') }} – {{ $r->end_date->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $r->reason }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('work-from-home.approve', $r) }}">
                                            @csrf
                                            <button class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-500">Approve</button>
                                        </form>
                                        <button type="button" @click="rejecting = rejecting === 'wfh-{{ $r->id }}' ? null : 'wfh-{{ $r->id }}'"
                                                class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500">Reject</button>
                                    </div>
                                    <form x-cloak x-show="rejecting === 'wfh-{{ $r->id }}'" method="POST" action="{{ route('work-from-home.reject', $r) }}" class="mt-2 flex items-center gap-2">
                                        @csrf
                                        <input type="text" name="review_notes" placeholder="Reason (optional)" maxlength="255" class="rounded-md border-gray-300 text-xs shadow-sm" />
                                        <button class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Confirm reject</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-gray-400">No pending WFH requests.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Quotations --}}
        <div class="rounded-lg bg-white shadow-sm">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">📄 Quotations</h2>
                <span class="text-sm text-gray-500">{{ $quotations->count() }} pending</span>
            </div>
            <div class="overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Client</th>
                            <th class="px-6 py-3">Number</th>
                            <th class="px-6 py-3">Amount</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($quotations as $q)
                            <tr>
                                <td class="px-6 py-3 text-gray-700">
                                    <a href="{{ route('quotations.show', $q) }}" class="text-indigo-600 hover:underline">{{ $q->customer?->company_name ?? 'Client removed' }}</a>
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $q->number ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ \App\Support\Money::format($q->total) }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('quotations.approve', $q) }}">
                                            @csrf
                                            <button class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-500">Approve</button>
                                        </form>
                                        <button type="button" @click="rejecting = rejecting === 'q-{{ $q->id }}' ? null : 'q-{{ $q->id }}'"
                                                class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500">Reject</button>
                                        <button type="button" @click="requestingChanges = requestingChanges === 'q-{{ $q->id }}' ? null : 'q-{{ $q->id }}'"
                                                class="rounded-md bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-400">Request changes</button>
                                    </div>
                                    <form x-cloak x-show="rejecting === 'q-{{ $q->id }}'" method="POST" action="{{ route('quotations.reject', $q) }}" class="mt-2 flex items-center gap-2">
                                        @csrf
                                        <input type="text" name="approval_notes" placeholder="Reason (optional)" maxlength="500" class="rounded-md border-gray-300 text-xs shadow-sm" />
                                        <button class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Confirm reject</button>
                                    </form>
                                    <form x-cloak x-show="requestingChanges === 'q-{{ $q->id }}'" method="POST" action="{{ route('quotations.request-changes', $q) }}" class="mt-2 flex items-center gap-2">
                                        @csrf
                                        <input type="text" name="approval_notes" placeholder="What needs to change? (required)" maxlength="500" required class="rounded-md border-gray-300 text-xs shadow-sm" />
                                        <button class="rounded-md border border-amber-300 px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50">Confirm</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-6 text-center text-gray-400">No pending quotations.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Project Updates --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">📝 Project Updates</h2>
                <span class="text-sm text-gray-500">{{ $projectsWithUpdates->count() }} {{ Str::plural('project', $projectsWithUpdates->count()) }} awaiting review</span>
            </div>
            @forelse ($projectsWithUpdates as $project)
                <div>
                    <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-gray-700 hover:underline">{{ $project->name }}</a>
                    <span class="text-xs text-gray-400">— {{ $project->customer?->company_name ?? 'Client removed' }}</span>
                    <livewire:project-daily-update-review :project="$project" :key="'padr-'.$project->id" />
                </div>
            @empty
                <div class="rounded-lg bg-white p-6 text-center text-sm text-gray-400 shadow-sm">No project updates awaiting review.</div>
            @endforelse
        </div>
    </div>

    {{-- The pending-approvals count and project list above are static
         Blade, not part of the embedded Livewire component — approving or
         discarding a project update only re-renders that component's own
         DOM, so refresh the whole page to keep the count honest. --}}
    <script>
        document.addEventListener('approval-center-refresh', () => window.location.reload());
    </script>
</x-app-layout>
