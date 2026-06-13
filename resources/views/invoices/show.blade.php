<x-app-layout>
    <x-slot name="header">Invoice {{ $invoice->invoice_number }}</x-slot>

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
                    <h1 class="text-xl font-semibold text-gray-900">{{ $invoice->invoice_number }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $invoice->customer->company_name }} ·
                        <span class="font-medium">{{ $invoice->status->label() }}</span> ·
                        Issued {{ $invoice->issue_date->format('d M Y') }}
                        @if ($invoice->due_date) · Due {{ $invoice->due_date->format('d M Y') }} @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @can('view', $invoice)
                        <form method="POST" action="{{ route('invoices.send', $invoice) }}">
                            @csrf
                            <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Send Invoice</button>
                        </form>
                    @endcan
                    <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Download PDF</a>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-4 text-sm">
                <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Total</div><div class="font-semibold">{{ \App\Support\Money::format($invoice->total) }}</div></div>
                <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Paid</div><div class="font-semibold">{{ \App\Support\Money::format($invoice->amount_paid) }}</div></div>
                <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Balance</div><div class="font-semibold">{{ \App\Support\Money::format($invoice->balance()) }}</div></div>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr><th class="py-2">Description</th><th class="py-2">SAC</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">GST%</th><th class="py-2 text-right">Amount</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($invoice->items as $item)
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
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ \App\Support\Money::format($invoice->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Discount</dt><dd>− {{ \App\Support\Money::format($invoice->discount) }}</dd></div>
                    @if ($invoice->is_intra_state)
                        <div class="flex justify-between"><dt class="text-gray-500">CGST</dt><dd>{{ \App\Support\Money::format($invoice->cgst_total) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">SGST</dt><dd>{{ \App\Support\Money::format($invoice->sgst_total) }}</dd></div>
                    @else
                        <div class="flex justify-between"><dt class="text-gray-500">IGST</dt><dd>{{ \App\Support\Money::format($invoice->igst_total) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">Round off</dt><dd>{{ \App\Support\Money::format($invoice->round_off) }}</dd></div>
                    <div class="flex justify-between border-t border-gray-200 pt-1 font-semibold"><dt>Total</dt><dd>{{ \App\Support\Money::format($invoice->total) }}</dd></div>
                </dl>
            </div>
            <p class="mt-3 text-right text-xs text-gray-500">{{ $invoice->amountInWords() }}</p>
        </div>

        {{-- Payments --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Payments</h2>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @forelse ($invoice->payments as $payment)
                        <li class="flex items-center justify-between py-2">
                            <span>{{ \App\Support\Money::format($payment->amount) }} · {{ $payment->mode->label() }} {{ $payment->reference ? "($payment->reference)" : '' }}</span>
                            <span class="text-gray-400">{{ $payment->paid_on->format('d M Y') }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-400">No payments recorded.</li>
                    @endforelse
                </ul>
            </div>

            @can('recordPayment', $invoice)
                @if ($invoice->balance() > 0)
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h2 class="text-base font-semibold text-gray-900">Record payment</h2>
                        <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <x-input-label for="amount" value="Amount (₹)" />
                                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('amount')" />
                            </div>
                            <div>
                                <x-input-label for="paid_on" value="Date" />
                                <x-text-input id="paid_on" name="paid_on" type="date" class="mt-1 block w-full" :value="old('paid_on', now()->toDateString())" />
                            </div>
                            <div>
                                <x-input-label for="mode" value="Mode" />
                                <select id="mode" name="mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    @foreach ($modes as $mode)
                                        <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="reference" value="Reference" />
                                <x-text-input id="reference" name="reference" type="text" class="mt-1 block w-full" :value="old('reference')" />
                            </div>
                            <x-primary-button>Record</x-primary-button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
