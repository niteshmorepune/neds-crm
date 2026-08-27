<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quotation {{ $quotation->number }} — {{ config('company.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 py-10">
        <div class="mb-6 flex items-center justify-between">
            <img src="{{ asset('images/neds-logo.png') }}" alt="{{ config('company.name') }}" style="height:40px;width:auto">
            <a href="{{ route('quotations.public-download', $quotation->public_token) }}" target="_blank" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Download PDF</a>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Quotation {{ $quotation->number }}</h1>
                    <p class="text-sm text-gray-500">{{ $quotation->customer->company_name }} · {{ $quotation->status->label() }}</p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-500">Total</div>
                    <div class="text-lg font-semibold">{{ \App\Support\Money::format($quotation->total) }}</div>
                </div>
            </div>

            @if ($quotation->scope_of_work)
                <p class="mt-4 text-sm text-gray-700">{{ $quotation->scope_of_work }}</p>
            @endif

            <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr><th class="py-2">Description</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">Amount</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($quotation->items as $item)
                        <tr>
                            <td class="py-2">{{ $item->description }}</td>
                            <td class="py-2 text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                            <td class="py-2 text-right">{{ \App\Support\Money::format($item->rate) }}</td>
                            <td class="py-2 text-right">{{ \App\Support\Money::format($item->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($quotation->milestones->isNotEmpty())
                <div class="mt-6 border-t border-gray-100 pt-4">
                    <h2 class="text-sm font-semibold text-gray-900">Billing milestones</h2>
                    <ul class="mt-2 divide-y divide-gray-100 text-sm">
                        @foreach ($quotation->milestones as $m)
                            <li class="flex items-center justify-between py-2">
                                <span>{{ $m->title }} <span class="text-gray-400">· {{ \App\Support\Money::format($m->amount) }}</span></span>
                                @if ($m->isBilled())
                                    <span class="text-xs font-medium {{ $m->invoice->balance() <= 0 ? 'text-green-700' : 'text-amber-700' }}">
                                        {{ $m->invoice->balance() <= 0 ? 'Paid' : 'Invoiced — payment pending' }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">Not yet due</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($milestone)
                <div class="mt-6 border-t border-gray-100 pt-4">
                    @if ($razorpayConfigured)
                        <button type="button" x-data x-on:click="$dispatch('open-razorpay')" class="w-full rounded-md bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500">
                            Pay {{ \App\Support\Money::format($milestone->amount) }} now — {{ $milestone->title }}
                        </button>
                    @else
                        <p class="text-sm text-gray-500">To pay the {{ $milestone->title }} ({{ \App\Support\Money::format($milestone->amount) }}), please contact us — online payment isn't available right now.</p>
                    @endif
                </div>
            @endif
        </div>

        <p class="mt-4 text-center text-xs text-gray-400">© {{ date('Y') }} {{ config('company.name') }}</p>
    </div>

    @if ($milestone && $razorpayConfigured)
        <div x-data="razorpayPay({
                orderUrl: '{{ route('quotations.public-pay.order', $quotation->public_token) }}',
                verifyUrl: '{{ route('quotations.public-pay.verify', $quotation->public_token) }}',
             })"
             x-on:open-razorpay.window="pay()">
            <div x-show="loading" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40">
                <div class="rounded-lg bg-white px-6 py-4 text-sm font-medium text-gray-700 shadow-xl">Preparing secure payment…</div>
            </div>
            <div x-show="error" style="display:none" class="max-w-3xl mx-auto mt-4 px-4">
                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" x-text="error"></div>
            </div>
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
                                description: order.milestone_title + ' — Quotation ' + (order.invoice_number || ''),
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
</body>
</html>
