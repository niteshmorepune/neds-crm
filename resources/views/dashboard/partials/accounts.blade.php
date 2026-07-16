<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Outstanding receivables</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ \App\Support\Money::format($stats['outstanding']) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Collected this month</p>
        <p class="mt-2 text-3xl font-semibold text-green-600">{{ \App\Support\Money::format($stats['collected_this_month']) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Overdue invoices</p>
        <p class="mt-2 text-3xl font-semibold text-red-600">{{ number_format($stats['overdue_count']) }}</p>
    </div>
</div>

<div class="rounded-lg bg-white p-4 shadow-sm flex flex-wrap items-center justify-between gap-3">
    <span class="text-sm text-gray-600">Review outstanding invoices and record payments.</span>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reports.receivables') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Receivables report</a>
        <a href="{{ route('reports.revenue') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Revenue report</a>
        <a href="{{ route('reports.business-overview') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Business overview</a>
        <a href="{{ route('reports.cash-forecast') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cash forecast</a>
    </div>
</div>
