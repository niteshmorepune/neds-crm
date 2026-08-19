<x-partner-portal-app-layout :header="$customer->company_name">

    <a href="{{ route('partner-portal.home') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to dashboard</a>

    <div class="mt-4 grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
            <p class="mt-1 text-lg font-semibold text-gray-900">{{ $customer->status->label() }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-500">Outstanding</p>
            <p class="mt-1 text-lg font-semibold text-gray-900">{{ \App\Support\Money::format($account['outstanding_amount']) }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-500">Overdue invoices</p>
            <p class="mt-1 text-lg font-semibold {{ $account['overdue_count'] > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $account['overdue_count'] }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h3 class="text-base font-semibold text-gray-900">Invoices</h3>
        <p class="mt-1 text-sm text-gray-500">Follow up directly with {{ $customer->company_name }} on anything shown as overdue.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">Invoice</th>
                        <th class="py-2">Status</th>
                        <th class="py-2">Due date</th>
                        <th class="py-2 text-right">Amount</th>
                        <th class="py-2 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($account['invoices'] as $invoice)
                        <tr>
                            <td class="py-2 font-medium text-gray-900">{{ $invoice->invoice_number }}</td>
                            <td class="py-2 text-gray-600">{{ $invoice->status->label() }}</td>
                            <td class="py-2 text-gray-600">{{ $invoice->status === \App\Enums\InvoiceStatus::Paid ? '—' : ($invoice->due_date?->format('d M Y') ?? '—') }}</td>
                            <td class="py-2 text-right text-gray-700">{{ \App\Support\Money::format($invoice->total) }}</td>
                            <td class="py-2 text-right text-gray-700">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-400">No invoices for this client yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Quotations</h3>
            @if ($quotations->isNotEmpty())
                @php $statusCounts = $quotations->countBy(fn ($q) => $q->status->value); @endphp
                <p class="text-xs text-gray-500">
                    {{ $statusCounts->get('accepted', 0) }} accepted ·
                    {{ $statusCounts->get('sent', 0) }} sent ·
                    {{ $statusCounts->get('rejected', 0) }} rejected ·
                    {{ $statusCounts->get('draft', 0) }} draft
                </p>
            @endif
        </div>

        <div class="mt-4 divide-y divide-gray-100">
            @forelse ($quotations as $quotation)
                <div class="flex items-center justify-between py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $quotation->number ?? '—' }}</p>
                        <p class="text-xs text-gray-400">{{ $quotation->status->label() }} · {{ \App\Support\Money::format($quotation->total) }}</p>
                    </div>
                    <a href="{{ route('partner-portal.quotations.pdf', $quotation) }}" class="shrink-0 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                        Download PDF
                    </a>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">No quotations for this client yet.</p>
            @endforelse
        </div>
    </div>

    @include('partner-portal.clients._services', ['customer' => $customer])

    @include('partner-portal.clients._monthly-collections', ['settlementGrid' => $settlementGrid])

</x-partner-portal-app-layout>
