<x-portal-app-layout :header="'Invoice '.$invoice->invoice_number">
    @php
        $canPayOnline = $invoice->status->isPayable() && $invoice->balance() > 0 && config('services.razorpay.key_id');
    @endphp

    <div class="rounded-lg bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">
                Issued {{ $invoice->issue_date->format('d M Y') }}@if ($invoice->due_date) · Due {{ $invoice->due_date->format('d M Y') }}@endif · {{ $invoice->status === \App\Enums\InvoiceStatus::Sent ? 'Unpaid' : $invoice->status->label() }}
            </p>
            <div class="flex items-center gap-2">
                @if ($canPayOnline)
                    <button type="button" x-data x-on:click="$dispatch('open-razorpay')" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Pay Now</button>
                @endif
                <a href="{{ route('portal.invoices.pdf', $invoice->id) }}" target="_blank" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Download PDF</a>
            </div>
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

    @if ($canPayOnline)
        <div x-data="razorpayPay({
                orderUrl: '{{ route('portal.invoices.pay.order', $invoice->id) }}',
                verifyUrl: '{{ route('portal.invoices.pay.verify', $invoice->id) }}',
             })"
             x-on:open-razorpay.window="pay()">
            <div x-show="loading" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40">
                <div class="rounded-lg bg-white px-6 py-4 text-sm font-medium text-gray-700 shadow-xl">Preparing secure payment…</div>
            </div>
            <div x-show="error" style="display:none" class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" x-text="error"></div>
        </div>

        <script>
            function razorpayPay({ orderUrl, verifyUrl }) {
                return {
                    loading: false,
                    error: null,
                    csrfToken() {
                        return document.querySelector('meta[name=csrf-token]').content;
                    },
                    loadCheckoutScript() {
                        if (window.Razorpay) return Promise.resolve();
                        return new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = 'https://checkout.razorpay.com/v1/checkout.js';
                            script.onload = resolve;
                            script.onerror = () => reject(new Error('Could not load the payment window.'));
                            document.head.appendChild(script);
                        });
                    },
                    async pay() {
                        this.error = null;
                        this.loading = true;
                        try {
                            await this.loadCheckoutScript();

                            const orderRes = await fetch(orderUrl, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': this.csrfToken(), 'Accept': 'application/json' },
                            });
                            const order = await orderRes.json();
                            if (! orderRes.ok) throw new Error(order.message || 'Could not start the payment.');

                            this.loading = false;

                            const rzp = new Razorpay({
                                key: order.key_id,
                                order_id: order.order_id,
                                amount: order.amount,
                                currency: 'INR',
                                name: order.company_name,
                                description: 'Invoice ' + order.invoice_number,
                                prefill: {
                                    name: order.contact_name || '',
                                    email: order.contact_email || '',
                                    contact: order.contact_phone || '',
                                },
                                handler: async (response) => {
                                    this.loading = true;
                                    try {
                                        const verifyRes = await fetch(verifyUrl, {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': this.csrfToken(),
                                                'Accept': 'application/json',
                                                'Content-Type': 'application/json',
                                            },
                                            body: JSON.stringify(response),
                                        });
                                        const result = await verifyRes.json();
                                        if (! verifyRes.ok) throw new Error(result.message || 'Payment could not be verified.');
                                        window.location.reload();
                                    } catch (e) {
                                        this.loading = false;
                                        this.error = e.message + ' If the amount was deducted, it will reflect here shortly — contact us if it does not.';
                                    }
                                },
                                modal: {
                                    ondismiss: () => { this.loading = false; },
                                },
                            });
                            rzp.on('payment.failed', (resp) => {
                                this.loading = false;
                                this.error = resp.error?.description || 'Payment failed. Please try again.';
                            });
                            rzp.open();
                        } catch (e) {
                            this.loading = false;
                            this.error = e.message;
                        }
                    },
                };
            }
        </script>
    @endif
</x-portal-app-layout>
