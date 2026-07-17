<x-app-layout>
    <x-slot name="header">Invoice {{ $invoice->invoice_number ?? '—' }}</x-slot>

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
                    <h1 class="text-xl font-semibold text-gray-900">{{ $invoice->invoice_number ?? 'Invoice' }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $invoice->customer?->company_name ?? 'Client removed' }} ·
                        <span class="font-medium">{{ $invoice->status->label() }}</span> ·
                        Issued {{ $invoice->issue_date->format('d M Y') }}
                        @if ($invoice->due_date && $invoice->status !== \App\Enums\InvoiceStatus::Paid) · Due {{ $invoice->due_date->format('d M Y') }} @endif
                    </p>
                    @if ($invoice->deal || $invoice->project)
                        <p class="mt-1 text-sm text-gray-400">
                            @if ($invoice->deal)
                                Deal: <a href="{{ route('deals.show', $invoice->deal) }}" class="text-indigo-600 hover:underline">{{ $invoice->deal->title }}</a>
                            @endif
                            @if ($invoice->deal && $invoice->project) · @endif
                            @if ($invoice->project)
                                Project: <a href="{{ route('projects.show', $invoice->project) }}" class="text-indigo-600 hover:underline">{{ $invoice->project->name }}</a>
                            @endif
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($invoice->status === \App\Enums\InvoiceStatus::Draft)
                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800">Draft — not visible to client</span>
                    @endif
                    @if ($invoice->promiseBroken())
                        <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-800">
                            Payment promise broken — was due {{ $invoice->payment_promised_date->format('d M Y') }}
                        </span>
                    @endif
                    @can('recordPayment', $invoice)
                        @if ($invoice->status === \App\Enums\InvoiceStatus::Draft)
                            <form method="POST" action="{{ route('invoices.send', $invoice) }}">
                                @csrf
                                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                        onclick="return confirm('Send this invoice to the client and mark it as Sent?')">Send Invoice</button>
                            </form>
                        @endif
                    @endcan
                    @can('update', $invoice)
                        <a href="{{ route('invoices.edit', $invoice) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                    @endcan
                    @can('delete', $invoice)
                        <button type="submit" form="delete-invoice" class="text-sm font-medium text-red-600 hover:text-red-500"
                                onclick="return confirm(@js($invoice->payments->isNotEmpty() ? 'This invoice has payment(s) recorded. Deleting it will also remove those payment records. Continue?' : 'Delete this invoice?'))">Delete</button>
                    @endcan
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Total</div><div class="font-semibold">{{ \App\Support\Money::format($invoice->total) }}</div></div>
                <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Paid</div><div class="font-semibold">{{ \App\Support\Money::format($invoice->amount_paid) }}</div></div>
                <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">TDS</div><div class="font-semibold">{{ \App\Support\Money::format($invoice->tdsTotal()) }}</div></div>
                <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Balance</div><div class="font-semibold">{{ \App\Support\Money::format($invoice->balance()) }}</div></div>
            </div>
        </div>

        {{-- Line items — shown only for invoices that have them (pre-log-simplification) --}}
        @if ($invoice->items->isNotEmpty())
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
        @endif

        {{-- Payments --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Payments</h2>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @forelse ($invoice->payments as $payment)
                        <li class="flex items-center justify-between py-2">
                            <span>
                                {{ \App\Support\Money::format($payment->amount) }} · {{ $payment->mode->label() }} {{ $payment->reference ? "($payment->reference)" : '' }}
                                @if ($payment->tds_amount > 0)
                                    <span class="text-gray-400">(TDS: {{ \App\Support\Money::format($payment->tds_amount) }})</span>
                                @endif
                            </span>
                            <span class="text-gray-400">{{ $payment->paid_on->format('d M Y') }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-400">No payments recorded.</li>
                    @endforelse
                </ul>
            </div>

            @can('recordPayment', $invoice)
                @if ($invoice->balance() > 0)
                    <div class="rounded-lg bg-white p-6 shadow-sm space-y-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Payment follow-up</h2>
                            <form method="POST" action="{{ route('invoices.payment-promise.update', $invoice) }}" class="mt-3 flex items-end gap-2">
                                @csrf
                                <div class="flex-1">
                                    <x-input-label for="payment_promised_date" value="Client promised to pay by" />
                                    <x-text-input id="payment_promised_date" name="payment_promised_date" type="date" class="mt-1 block w-full"
                                                  :value="old('payment_promised_date', $invoice->payment_promised_date?->toDateString())" />
                                </div>
                                <x-primary-button>Save</x-primary-button>
                                @if ($invoice->payment_promised_date)
                                    <button type="submit" form="clear-payment-promise" class="text-sm text-gray-500 hover:text-gray-700">Clear</button>
                                @endif
                            </form>
                            @if ($invoice->payment_promised_date)
                                <form id="clear-payment-promise" method="POST" action="{{ route('invoices.payment-promise.update', $invoice) }}" class="hidden">
                                    @csrf
                                </form>
                            @endif
                        </div>
                        <h2 class="text-base font-semibold text-gray-900">Record payment</h2>
                        <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <x-input-label for="amount" value="Amount (₹)" />
                                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('amount')" />
                            </div>
                            <div>
                                <x-input-label for="tds_amount" value="TDS Amount (₹) — optional" />
                                <x-text-input id="tds_amount" name="tds_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('tds_amount')" />
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
                            @if ($invoice->customer?->contacts->firstWhere(fn($c) => filled($c->email)))
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="send_receipt" name="send_receipt" value="1"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                       {{ old('send_receipt') ? 'checked' : '' }} />
                                <label for="send_receipt" class="text-sm text-gray-700 select-none cursor-pointer">Send payment receipt to client</label>
                            </div>
                            @endif
                            <x-primary-button>Record</x-primary-button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>

        @can('recordPayment', $invoice)
            <livewire:record-notes :record="$invoice" :can-manage="true" />
        @endcan

        @can('delete', $invoice)
            <form id="delete-invoice" method="POST" action="{{ route('invoices.destroy', $invoice) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endcan
    </div>
</x-app-layout>
