{{--
    Shared Razorpay Orders + Checkout.js flow for the 3 new offer pages —
    same shape as resources/views/quotations/public-view.blade.php's own
    inline script, generalized. Expects $offerKey (App\Enums\OfferKey) and
    $leadId (nullable int) from the including view; open the checkout by
    dispatching a window "open-offer-checkout" event from the page's own CTA
    button (x-on:click="$dispatch('open-offer-checkout')").

    Pass $websiteUrlFieldId (a DOM id string) when this offer collects an
    extra field before payment — currently only Website Growth Audit's own
    website_url input (OfferKey::collectsWebsiteUrl()). Omitted entirely for
    Lead Generation Audit/Growth Strategy, which send no extra body at all,
    unchanged from before this field existed.
--}}
@if ($razorpayConfigured)
    <div x-data="offerCheckout({
            orderUrl: '{{ route('offers.checkout.order', $offerKey->value) }}',
            verifyUrl: '{{ route('offers.checkout.verify', $offerKey->value) }}',
            failedUrl: '{{ route('offers.checkout.failed', $offerKey->value) }}',
            leadId: {{ $leadId ?? 'null' }},
            websiteUrlFieldId: @json($websiteUrlFieldId ?? null),
         })"
         x-on:open-offer-checkout.window="pay()">
        <div x-show="loading" style="display:none;position:fixed;inset:0;z-index:50;align-items:center;justify-content:center;background:rgba(7,29,73,.45)">
            <div style="background:#fff;border-radius:14px;padding:18px 22px;font-weight:700;color:#344054;box-shadow:0 20px 50px rgba(7,29,73,.25)">Preparing secure payment…</div>
        </div>
        <div x-show="error" style="display:none" x-text="error" class="buyerror"></div>
        <div x-show="success" style="display:none;background:#ecfdf3;border-color:#abefc6;color:#067647" class="buyerror">
            Payment successful! Our team will reach out on WhatsApp/phone shortly with the next steps.
        </div>
    </div>

    <script>
        function offerCheckout({ orderUrl, verifyUrl, failedUrl, leadId, websiteUrlFieldId }) {
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
                async pay() {
                    this.error = null;
                    this.success = false;

                    let websiteUrl = null;
                    if (websiteUrlFieldId) {
                        const field = document.getElementById(websiteUrlFieldId);
                        websiteUrl = field ? field.value.trim() : '';
                        if (! websiteUrl) {
                            this.error = 'Please enter your website URL first.';
                            field?.focus();
                            return;
                        }
                    }

                    this.loading = true;
                    try {
                        await this.loadCheckoutScript();

                        const orderRes = await fetch(this.withLead(orderUrl), {
                            method: 'POST',
                            headers: websiteUrl
                                ? { 'X-CSRF-TOKEN': this.csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' }
                                : { 'X-CSRF-TOKEN': this.csrfToken(), 'Accept': 'application/json' },
                            ...(websiteUrl ? { body: JSON.stringify({ website_url: websiteUrl }) } : {}),
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
                            fetch(this.withLead(failedUrl), {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': this.csrfToken(), 'Accept': 'application/json' },
                            }).catch(() => {});
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
