{{--
    In-app Razorpay Orders + Checkout.js flow for the GBP Visibility Audit
    offer specifically — same shape as offers/partials/checkout-script.blade.php
    (the 3 newer offers' own shared script), duplicated rather than
    generalized because this offer needs one extra piece of information
    Checkout.js can't collect itself: the payer's Google Business Profile /
    Maps link. Expects $leadId (nullable int) from the including view.
    Open the checkout by dispatching a window "open-va-checkout" event from
    the page's own CTA buttons.
--}}
@if ($razorpayConfigured)
    <div x-data="visibilityAuditCheckout({
            orderUrl: '{{ route('offers.visibility-audit.order') }}',
            verifyUrl: '{{ route('offers.visibility-audit.verify') }}',
            leadId: {{ $leadId ?? 'null' }},
         })"
         x-on:open-va-checkout.window="pay()">
        <div x-show="loading" style="display:none;position:fixed;inset:0;z-index:50;align-items:center;justify-content:center;background:rgba(7,29,73,.45)">
            <div style="background:#fff;border-radius:14px;padding:18px 22px;font-weight:700;color:#344054;box-shadow:0 20px 50px rgba(7,29,73,.25)">Preparing secure payment…</div>
        </div>
        <div x-show="error" style="display:none" x-text="error" class="buyerror"></div>
        <div x-show="success" style="display:none;background:#ecfdf3;border-color:#abefc6;color:#067647" class="buyerror">
            Payment successful! Our team will reach out on WhatsApp/phone shortly to begin your audit.
        </div>
    </div>

    <script>
        function visibilityAuditCheckout({ orderUrl, verifyUrl, leadId }) {
            return {
                loading: false,
                error: null,
                success: false,
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
                withLead(url) {
                    return leadId ? (url + (url.includes('?') ? '&' : '?') + 'lead=' + leadId) : url;
                },
                gbpUrlValue() {
                    const field = document.getElementById('gbp_url');
                    return field ? field.value.trim() : '';
                },
                async pay() {
                    this.error = null;
                    this.success = false;

                    const gbpUrl = this.gbpUrlValue();
                    if (! gbpUrl) {
                        this.error = 'Please enter your Google Business Profile or Maps link first.';
                        document.getElementById('gbp_url')?.focus();
                        return;
                    }

                    this.loading = true;
                    try {
                        await this.loadCheckoutScript();

                        const orderRes = await fetch(this.withLead(orderUrl), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ gbp_url: gbpUrl }),
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
                            description: order.offer_name,
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
                                    this.loading = false;
                                    this.success = true;
                                } catch (e) {
                                    this.loading = false;
                                    this.error = e.message + ' If the amount was deducted, it will reflect shortly — contact us if it does not.';
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
