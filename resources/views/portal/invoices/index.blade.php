<x-portal-app-layout header="Your Invoices">
    @forelse ($invoices as $invoice)
        {{-- Mobile card --}}
        <div class="sm:hidden mb-3 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <a href="{{ route('portal.invoices.show', $invoice->id) }}"
                       class="font-semibold text-indigo-600 hover:underline text-sm">{{ $invoice->invoice_number }}</a>
                    <p class="text-xs text-gray-400 mt-0.5">Issued {{ $invoice->issue_date->format('d M Y') }}</p>
                </div>
                @php
                    $statusColor = match($invoice->status->value) {
                        'paid'           => 'bg-green-100 text-green-700',
                        'overdue'        => 'bg-red-100 text-red-700',
                        'partially_paid' => 'bg-amber-100 text-amber-700',
                        default          => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <span class="shrink-0 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                    {{ $invoice->status->label() }}
                </span>
            </div>
            <div class="mt-3 flex items-center justify-between text-sm">
                <div>
                    <span class="text-gray-400 text-xs">Total</span>
                    <p class="font-semibold text-gray-900">{{ \App\Support\Money::format($invoice->total) }}</p>
                </div>
                <div class="text-right">
                    <span class="text-gray-400 text-xs">Balance</span>
                    <p class="font-semibold {{ $invoice->balance() > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ \App\Support\Money::format($invoice->balance()) }}</p>
                </div>
                <a href="{{ route('portal.invoices.pdf', $invoice->id) }}" target="_blank"
                   class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Download PDF</a>
            </div>
        </div>

        {{-- Desktop table row (hidden on mobile) --}}
    @empty
    @endforelse

    {{-- Desktop table --}}
    <div class="hidden sm:block overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-3">Invoice #</th>
                    <th class="px-5 py-3">Issued</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Total</th>
                    <th class="px-5 py-3 text-right">Balance</th>
                    <th class="px-5 py-3 text-right">PDF</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($invoices as $invoice)
                    @php
                        $statusColor = match($invoice->status->value) {
                            'paid'           => 'bg-green-100 text-green-700',
                            'overdue'        => 'bg-red-100 text-red-700',
                            'partially_paid' => 'bg-amber-100 text-amber-700',
                            default          => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('portal.invoices.show', $invoice->id) }}"
                               class="font-semibold text-indigo-600 hover:underline">{{ $invoice->invoice_number }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $invoice->issue_date->format('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">{{ $invoice->status->label() }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-right text-gray-700 font-medium">{{ \App\Support\Money::format($invoice->total) }}</td>
                        <td class="px-5 py-3.5 text-right font-medium {{ $invoice->balance() > 0 ? 'text-red-600' : 'text-gray-700' }}">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('portal.invoices.pdf', $invoice->id) }}" target="_blank"
                               class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Download</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400 text-sm">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Empty state mobile --}}
    @if($invoices->isEmpty())
        <div class="sm:hidden rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-100">
            <p class="text-sm text-gray-400">No invoices yet.</p>
        </div>
    @endif

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-portal-app-layout>
