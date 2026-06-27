<x-portal-app-layout :header="'Invoice '.$invoice->invoice_number">
    <div class="rounded-lg bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">
                Issued {{ $invoice->issue_date->format('d M Y') }}@if ($invoice->due_date) · Due {{ $invoice->due_date->format('d M Y') }}@endif · {{ $invoice->status === \App\Enums\InvoiceStatus::Sent ? 'Unpaid' : $invoice->status->label() }}
            </p>
            <a href="{{ route('portal.invoices.pdf', $invoice->id) }}" target="_blank" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Download PDF</a>
        </div>

        <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
            <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr><th class="py-2">Description</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">Amount</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2 text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                        <td class="py-2 text-right">{{ \App\Support\Money::format($item->rate) }}</td>
                        <td class="py-2 text-right">{{ \App\Support\Money::format($item->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 flex justify-end">
            <dl class="w-56 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd class="font-semibold">{{ \App\Support\Money::format($invoice->total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Paid</dt><dd>{{ \App\Support\Money::format($invoice->amount_paid) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Balance</dt><dd>{{ \App\Support\Money::format($invoice->balance()) }}</dd></div>
            </dl>
        </div>
    </div>
    <div class="mt-4"><a href="{{ route('portal.invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to invoices</a></div>
</x-portal-app-layout>
