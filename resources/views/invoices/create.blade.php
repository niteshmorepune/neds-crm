<x-app-layout>
    <x-slot name="header">Log Invoice</x-slot>

    @php
        $selectedCustomerId = old('customer_id', $prefillCustomerId);
        $selectedProjectId = old('project_id', $prefillProjectId);
        $defaultItems = [['description' => '', 'sac_code' => $defaultSacCode, 'quantity' => '', 'rate' => '', 'gst_rate' => '18']];
    @endphp

    <div class="max-w-3xl mx-auto"
         x-data="{
            itemsMode: {{ old('mode') === 'items' ? 'true' : 'false' }},
            customerStates: @js($customers->mapWithKeys(fn ($c) => [(string) $c->id => $c->state_code])),
            companyStateCode: '{{ config('india.company_state_code') }}',
            selectedCustomerId: '{{ $selectedCustomerId }}',
            items: @js(old('items') ?: $defaultItems),
            discount: '{{ old('discount', '0') }}',
            isGstExempt: {{ old('is_gst_exempt') ? 'true' : 'false' }},
            filterByCustomer(cid, resetSelection) {
                this.selectedCustomerId = cid;
                document.querySelectorAll('#deal_id option, #project_id option').forEach(o => {
                    o.hidden = o.value !== '' && o.dataset.customer !== cid;
                });
                if (resetSelection) {
                    document.getElementById('deal_id').value = '';
                    document.getElementById('project_id').value = '';
                }
            },
            addItem() {
                this.items.push({ description: '', sac_code: @js($defaultSacCode), quantity: '', rate: '', gst_rate: '18' });
            },
            removeItem(index) {
                this.items.splice(index, 1);
                if (this.items.length === 0) this.addItem();
            },
            lineAmount(item) {
                return (parseFloat(item.quantity) || 0) * (parseFloat(item.rate) || 0);
            },
            get subtotal() {
                return this.items.reduce((sum, item) => sum + this.lineAmount(item), 0);
            },
            get taxable() {
                return Math.max(0, this.subtotal - (parseFloat(this.discount) || 0));
            },
            get isIntraState() {
                const state = this.customerStates[this.selectedCustomerId];
                return !state || state === this.companyStateCode;
            },
            get estimatedTax() {
                if (this.isGstExempt) return 0;
                // Per-line tax on the pre-discount line amount -- a simplification
                // vs. the server's discount-prorated math (GstCalculator), fine for
                // a live estimate since the actual save always recalculates
                // authoritatively server-side.
                return this.items.reduce((sum, item) => sum + (this.lineAmount(item) * (parseFloat(item.gst_rate) || 0) / 100), 0);
            },
            get estimatedTotal() {
                return this.taxable + this.estimatedTax;
            },
            money(n) {
                return isNaN(n) ? '₹0.00' : '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
         }">
        <form method="POST" action="{{ route('invoices.store') }}" class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="mode" :value="itemsMode ? 'items' : 'flat'">

            @if ($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div>
                <x-input-label for="invoice_number" value="Hitech Invoice Number *" />
                <x-text-input id="invoice_number" name="invoice_number" type="text" class="mt-1 block w-full"
                              :value="old('invoice_number')" placeholder="e.g. HT-2026-0042" required />
                <x-input-error :messages="$errors->get('invoice_number')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="customer_id" value="Client *" />
                <select id="customer_id" name="customer_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        x-init="if ($el.value) { filterByCustomer($el.value, false) }"
                        x-on:change="filterByCustomer($event.target.value, true)">
                    <option value="">— Select client —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected($selectedCustomerId == $customer->id)>{{ $customer->company_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="deal_id" value="Deal (optional)" />
                    <select id="deal_id" name="deal_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        @foreach ($deals as $deal)
                            <option value="{{ $deal->id }}" data-customer="{{ $deal->customer_id }}"
                                    @selected(old('deal_id') == $deal->id)>{{ $deal->title }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('deal_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="project_id" value="Project (optional)" />
                    <select id="project_id" name="project_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" data-customer="{{ $project->customer_id }}"
                                    @selected($selectedProjectId == $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('project_id')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="issue_date" value="Invoice Date *" />
                    <x-text-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                                  :value="old('issue_date', now()->toDateString())" required />
                    <x-input-error :messages="$errors->get('issue_date')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="due_date" value="Due Date" />
                    <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full"
                                  :value="old('due_date')" />
                    <x-input-error :messages="$errors->get('due_date')" class="mt-1" />
                </div>
            </div>

            <div class="flex items-center gap-2 border-t border-gray-100 pt-4">
                <input type="checkbox" id="items_mode_toggle" x-model="itemsMode"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <label for="items_mode_toggle" class="text-sm font-medium text-gray-700 select-none cursor-pointer">
                    Add GST line items now (SAC/HSN, rate, GST% — for an itemized tax invoice)
                </label>
            </div>

            <div x-show="!itemsMode">
                <x-input-label for="amount" value="Amount (₹) *" />
                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                              class="mt-1 block w-full" :value="old('amount')" placeholder="50000" :required="old('mode') !== 'items'" />
                <x-input-error :messages="$errors->get('amount')" class="mt-1" />
            </div>

            <div x-show="itemsMode" x-cloak class="space-y-4 rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Line items</h3>
                    <button type="button" x-on:click="addItem()" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">+ Add item</button>
                </div>
                @error('items') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="hidden md:flex flex-nowrap items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-400">
                    <div class="flex-1 min-w-0">Description</div>
                    <div class="w-24 shrink-0">SAC/HSN</div>
                    <div class="w-14 shrink-0">Qty</div>
                    <div class="w-24 shrink-0">Rate ₹</div>
                    <div class="w-16 shrink-0">GST %</div>
                    <div class="w-24 shrink-0 text-right">Amount</div>
                    <div class="w-5 shrink-0"></div>
                </div>

                <template x-for="(item, index) in items" :key="index">
                    <div class="flex flex-wrap md:flex-nowrap items-center gap-2 border-b border-gray-100 pb-3">
                        <input type="text" x-model="item.description" :name="`items[${index}][description]`"
                               placeholder="Description" class="w-full md:flex-1 md:w-auto min-w-0 rounded-md border-gray-300 text-sm shadow-sm">
                        <input type="text" x-model="item.sac_code" :name="`items[${index}][sac_code]`"
                               placeholder="SAC/HSN" class="w-24 shrink-0 min-w-0 rounded-md border-gray-300 text-sm shadow-sm">
                        <input type="number" step="1" min="0" x-model="item.quantity" :name="`items[${index}][quantity]`"
                               placeholder="Qty" class="w-14 shrink-0 min-w-0 rounded-md border-gray-300 text-sm shadow-sm">
                        <input type="number" step="any" min="0" x-model="item.rate" :name="`items[${index}][rate]`"
                               placeholder="Rate ₹" class="flex-1 md:w-24 md:flex-none min-w-0 rounded-md border-gray-300 text-sm shadow-sm">
                        <input type="number" step="any" min="0" x-model="item.gst_rate" :name="`items[${index}][gst_rate]`"
                               placeholder="GST %" class="w-16 shrink-0 min-w-0 rounded-md border-gray-300 text-sm shadow-sm">
                        <span class="w-24 shrink-0 text-right text-sm font-medium text-gray-600" x-text="money(lineAmount(item))"></span>
                        <button type="button" x-on:click="removeItem(index)" class="w-5 shrink-0 text-lg leading-none text-red-600 hover:text-red-500">&times;</button>
                    </div>
                </template>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="discount" value="Discount (₹)" />
                        <input id="discount" name="discount" type="number" step="0.01" min="0" x-model="discount"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_gst_exempt" value="1" x-model="isGstExempt" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                            Non-GST invoice (don't charge GST)
                        </label>
                    </div>
                </div>

                <div class="rounded-md bg-gray-50 p-3 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span x-text="money(subtotal)"></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Estimated GST</span><span x-text="money(estimatedTax)"></span></div>
                    <div class="flex justify-between border-t border-gray-200 mt-1 pt-1 font-semibold"><span>Estimated total</span><span x-text="money(estimatedTotal)"></span></div>
                    <p class="mt-1 text-xs text-gray-400">Exact CGST/SGST/IGST split is calculated when you save.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button>Log Invoice</x-primary-button>
                <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
