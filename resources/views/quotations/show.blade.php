<x-app-layout>
    <x-slot name="header">Quotation {{ $quotation->number }}</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $quotation->number }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $quotation->customer->company_name }} ·
                        <span class="font-medium">{{ $quotation->status->label() }}</span> ·
                        {{ $quotation->is_intra_state ? 'Intra-state (CGST+SGST)' : 'Inter-state (IGST)' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @can('update', $quotation)
                        @if ($quotation->isEditable())
                            <a href="{{ route('quotations.edit', $quotation) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Edit</a>
                        @endif
                    @endcan

                    @foreach ($quotation->status->allowedTransitions() as $target)
                        <form method="POST" action="{{ route('quotations.status', $quotation) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $target->value }}" />
                            <button class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Mark {{ $target->label() }}
                            </button>
                        </form>
                    @endforeach

                    @if ($quotation->status === \App\Enums\QuotationStatus::Accepted && ! $quotation->invoice)
                        @can('convert', $quotation)
                            <form method="POST" action="{{ route('quotations.convert', $quotation) }}">
                                @csrf
                                <button class="rounded-md bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-500">Convert to invoice</button>
                            </form>
                        @endcan
                    @endif

                    @if ($quotation->invoice)
                        <a href="{{ route('invoices.show', $quotation->invoice) }}" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View invoice</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr><th class="py-2">Description</th><th class="py-2">SAC</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">GST%</th><th class="py-2 text-right">Amount</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($quotation->items as $item)
                        <tr>
                            <td class="py-2">{{ $item->description }}</td>
                            <td class="py-2 text-gray-500">{{ $item->sac_code ?? '—' }}</td>
                            <td class="py-2 text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                            <td class="py-2 text-right">{{ \App\Support\Money::format($item->rate) }}</td>
                            <td class="py-2 text-right">{{ rtrim(rtrim($item->gst_rate, '0'), '.') }}%</td>
                            <td class="py-2 text-right">{{ \App\Support\Money::format($item->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4 flex justify-end">
                <dl class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ \App\Support\Money::format($quotation->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Discount</dt><dd>− {{ \App\Support\Money::format($quotation->discount) }}</dd></div>
                    @if ($quotation->is_intra_state)
                        <div class="flex justify-between"><dt class="text-gray-500">CGST</dt><dd>{{ \App\Support\Money::format($quotation->cgst_total) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">SGST</dt><dd>{{ \App\Support\Money::format($quotation->sgst_total) }}</dd></div>
                    @else
                        <div class="flex justify-between"><dt class="text-gray-500">IGST</dt><dd>{{ \App\Support\Money::format($quotation->igst_total) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">Round off</dt><dd>{{ \App\Support\Money::format($quotation->round_off) }}</dd></div>
                    <div class="flex justify-between border-t border-gray-200 pt-1 font-semibold"><dt>Total</dt><dd>{{ \App\Support\Money::format($quotation->total) }}</dd></div>
                </dl>
            </div>
            <p class="mt-3 text-right text-xs text-gray-500">{{ $quotation->amountInWords() }}</p>

            @if ($quotation->terms)
                <div class="mt-4 border-t border-gray-100 pt-4 text-sm text-gray-600"><span class="font-medium">Terms:</span> {{ $quotation->terms }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
