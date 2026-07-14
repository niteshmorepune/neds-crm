<x-app-layout>
    <x-slot name="header">Collections</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <select name="partner_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="" @selected($selectedPartnerId === '')>All clients</option>
                    <option value="direct" @selected($selectedPartnerId === 'direct')>Direct clients (no partner)</option>
                    @foreach ($partners as $partner)
                        <option value="{{ $partner->id }}" @selected($selectedPartnerId === (string) $partner->id)>{{ $partner->name }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
        </div>

        {{-- Top stat cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Unpaid (recurring + other)</p>
                <p class="mt-2 text-2xl font-semibold text-red-600">{{ \App\Support\Money::format($rows->sum('recurring_overdue_amount') + $rows->sum('other_unpaid_amount')) }}</p>
                <p class="text-xs text-gray-400">
                    {{ $rows->sum('recurring_overdue_count') }} recurring · {{ $rows->sum('other_unpaid_count') }} other
                </p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Partial — pending</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($rows->sum('partial_amount')) }}</p>
                <p class="text-xs text-gray-400">{{ $rows->sum('partial_count') }} invoice(s)</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Broken payment promises</p>
                <p class="mt-2 text-2xl font-semibold text-red-600">{{ $rows->where('promise_broken', true)->count() }}</p>
                <p class="text-xs text-gray-400">clients past their promised date</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Milestones ready to invoice</p>
                <p class="mt-2 text-2xl font-semibold text-green-600">
                    {{ $rows->sum(fn ($row) => $row['projects']->filter(fn ($p) => $p['milestone']['ready_to_invoice'] ?? false)->count()) }}
                </p>
                <p class="text-xs text-gray-400">work marked done, not yet billed</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Client health</h3>
            <div class="mt-3">
                @include('collections._client-table', ['rows' => $rows, 'showPartnerColumn' => true])
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">See also</h3>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('reports.business-overview') }}" class="text-indigo-600 hover:underline">Business Overview</a></li>
                <li><a href="{{ route('reports.receivables') }}" class="text-indigo-600 hover:underline">Receivables</a></li>
                <li><a href="{{ route('partners.index') }}" class="text-indigo-600 hover:underline">Partners</a></li>
            </ul>
        </div>
    </div>
</x-app-layout>
