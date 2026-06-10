<x-portal-app-layout header="My Invoices">
    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr><th class="px-4 py-3">Invoice #</th><th class="px-4 py-3">Issued</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Balance</th><th class="px-4 py-3 text-right">PDF</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><a href="{{ route('portal.invoices.show', $invoice->id) }}" class="font-medium text-indigo-600 hover:underline">{{ $invoice->invoice_number }}</a></td>
                        <td class="px-4 py-3 text-gray-600">{{ $invoice->issue_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $invoice->status->label() }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ \App\Support\Money::format($invoice->total) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('portal.invoices.pdf', $invoice->id) }}" target="_blank" class="text-gray-500 hover:text-gray-700">Download</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-portal-app-layout>
