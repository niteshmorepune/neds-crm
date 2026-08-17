@php($monthLabel = $month === now()->format('Y-m') ? 'this month' : \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M Y'))

@if (in_array('outstanding', $visibleWidgets) || in_array('collected_this_month', $visibleWidgets) || in_array('overdue_invoices', $visibleWidgets) || in_array('unapplied_advances', $visibleWidgets))
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        @if (in_array('outstanding', $visibleWidgets))
            <a href="{{ route('reports.receivables') }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm text-gray-500">Outstanding receivables</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ \App\Support\Money::format($stats['outstanding']) }}</p>
            </a>
        @endif
        @if (in_array('collected_this_month', $visibleWidgets))
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Collected {{ $monthLabel }}</p>
                <p class="mt-2 text-3xl font-semibold text-green-600">{{ \App\Support\Money::format($stats['collected_this_month']) }}</p>
                <div class="mt-3">
                    <a href="{{ route('reports.collected') }}" class="text-sm text-indigo-600 hover:underline">View payments collected →</a>
                </div>
                <div class="mt-3">
                    <x-month-filter :month="$month" />
                </div>
            </div>
        @endif
        @if (in_array('overdue_invoices', $visibleWidgets))
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Overdue invoices</p>
                <p class="mt-2 text-3xl font-semibold text-red-600">{{ number_format($stats['overdue_count']) }}</p>
                <div class="mt-3">
                    <a href="{{ route('invoices.index', ['status' => \App\Enums\InvoiceStatus::Overdue->value]) }}" class="text-sm text-indigo-600 hover:underline">View overdue invoices →</a>
                </div>
            </div>
        @endif
        @if (in_array('unapplied_advances', $visibleWidgets))
            <a href="{{ route('reports.advances') }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm text-gray-500">Unapplied client advances</p>
                <p class="mt-2 text-3xl font-semibold text-blue-600">{{ \App\Support\Money::format($stats['unapplied_advances']) }}</p>
                <p class="mt-3 text-sm text-indigo-600">View advances →</p>
            </a>
        @endif
    </div>
@endif

@if (in_array('action_buttons', $visibleWidgets))
    <div class="rounded-lg bg-white p-4 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <span class="text-sm text-gray-600">Review outstanding invoices and record payments.</span>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.receivables') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Receivables report</a>
            <a href="{{ route('reports.revenue') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Revenue report</a>
            <a href="{{ route('reports.business-overview') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Business overview</a>
            <a href="{{ route('reports.cash-forecast') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cash forecast</a>
        </div>
    </div>
@endif

@if (in_array('my_productivity', $visibleWidgets))
    <livewire:my-productivity />
@endif

@if (in_array('target_progress', $visibleWidgets) && $targetProgress)
    <div class="max-w-sm rounded-lg bg-white p-4 shadow-sm">
        <p class="mb-3 text-xs font-medium text-gray-500">Your target this month</p>
        <x-target-progress-bar :metric="$targetProgress['metric']" :target="$targetProgress['target']" :actual="$targetProgress['actual']" :pct="$targetProgress['pct']" />
    </div>
@endif
