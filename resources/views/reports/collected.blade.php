<x-app-layout>
    <x-slot name="header">Collected This Month</x-slot>

    <div class="max-w-5xl mx-auto space-y-4">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="text-sm text-gray-500">Total collected</div>
            <div class="text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($total) }}</div>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Mode</th>
                        <th class="px-4 py-3">Recorded by</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $payment->paid_on->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($payment->invoice?->customer)
                                    <a href="{{ route('clients.show', $payment->invoice->customer) }}" class="text-indigo-600 hover:underline">{{ $payment->invoice->customer->company_name }}</a>
                                @else
                                    <span class="text-gray-400">Client removed</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($payment->invoice)
                                    <a href="{{ route('invoices.show', $payment->invoice) }}" class="text-indigo-600 hover:underline">{{ $payment->invoice->invoice_number ?? 'Pending #' }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $payment->mode->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $payment->recordedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900">{{ \App\Support\Money::format($payment->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No payments collected this month yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
