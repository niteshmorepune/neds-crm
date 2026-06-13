<?php // Live GST preview ?>
@php($t = $this->totals)

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">{{ $quotationId ? 'Edit' : 'New' }} Quotation</h1>
        <a href="{{ route('quotations.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
    </div>

    {{-- Totals fixed to lg:w-52 so the form section gets maximum room --}}
    <div class="flex flex-col lg:flex-row gap-6 items-start">
        <div class="w-full lg:flex-1 min-w-0 space-y-6">

            <div class="rounded-lg bg-white p-6 shadow-sm grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <x-input-label value="Client *" />
                    <select wire:model.live="customer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Select client</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->company_name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <x-input-label value="Validity date" />
                    <x-text-input wire:model="validity_date" type="date" class="mt-1 block w-full" />
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Line items</h2>
                    <button wire:click="addItem" type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">+ Add item</button>
                </div>
                @error('items') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                {{-- Column labels — only visible in single-row mode (2xl+) --}}
                <div class="hidden 2xl:flex flex-nowrap items-center gap-2 mt-4 mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">
                    <div class="flex-1 min-w-0">Description</div>
                    <div class="w-24 shrink-0">SAC/HSN</div>
                    <div class="w-14 shrink-0">Qty</div>
                    <div class="w-28 shrink-0">Rate ₹</div>
                    <div class="w-16 shrink-0">GST %</div>
                    <div class="w-24 shrink-0 text-right">Amount</div>
                    <div class="w-5 shrink-0"></div>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach ($items as $i => $item)
                        <div class="border-b border-gray-100 pb-3" wire:key="item-{{ $i }}">
                            {{--
                                flex-wrap  → small screens: Description forces its own row (w-full),
                                             other fields wrap to the next line with Rate taking flex-1.
                                xl:flex-nowrap → wide screens: all fields in one row, Description is flex-1.
                            --}}
                            <div class="flex flex-wrap 2xl:flex-nowrap items-center gap-2">
                                <input wire:model="items.{{ $i }}.description" placeholder="Description"
                                       class="w-full 2xl:flex-1 2xl:w-auto min-w-0 rounded-md border-gray-300 text-sm shadow-sm" />
                                @error("items.$i.description") <span class="w-full text-xs text-red-600">{{ $message }}</span> @enderror

                                <input wire:model="items.{{ $i }}.sac_code" placeholder="SAC/HSN"
                                       class="w-24 shrink-0 min-w-0 rounded-md border-gray-300 text-sm shadow-sm" />
                                <input wire:model.live="items.{{ $i }}.quantity" type="number" step="0.01" placeholder="Qty"
                                       class="w-14 shrink-0 min-w-0 rounded-md border-gray-300 text-sm shadow-sm" />
                                <input wire:model.live="items.{{ $i }}.rate" type="number" step="0.01" placeholder="Rate ₹"
                                       class="flex-1 2xl:w-28 2xl:flex-none min-w-0 rounded-md border-gray-300 text-sm shadow-sm" />
                                <input wire:model.live="items.{{ $i }}.gst_rate" type="number" step="0.01" placeholder="GST %"
                                       class="w-16 shrink-0 min-w-0 rounded-md border-gray-300 text-sm shadow-sm" />
                                <span class="w-24 shrink-0 text-right text-sm text-gray-600 font-medium">
                                    {{ \App\Support\Money::format($t['lines'][$i]['amount'] ?? 0) }}
                                </span>
                                <button wire:click="removeItem({{ $i }})" type="button"
                                        class="w-5 shrink-0 text-red-600 hover:text-red-500 text-lg leading-none text-center">&times;</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label value="Discount (₹)" />
                        <x-text-input wire:model.live="discount" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    </div>
                </div>

                <div class="mt-4">
                    <x-input-label value="Terms" />
                    <textarea wire:model="terms" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                </div>
            </div>
        </div>

        {{-- Live totals — fixed narrow width on lg+, full width below --}}
        <div class="w-full lg:w-52 shrink-0 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Totals</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ \App\Support\Money::format($t['subtotal']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Discount</dt><dd>− {{ \App\Support\Money::format($t['discount']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Taxable</dt><dd>{{ \App\Support\Money::format($t['taxable_total']) }}</dd></div>
                @if ($t['is_intra_state'])
                    <div class="flex justify-between"><dt class="text-gray-500">CGST</dt><dd>{{ \App\Support\Money::format($t['cgst_total']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">SGST</dt><dd>{{ \App\Support\Money::format($t['sgst_total']) }}</dd></div>
                @else
                    <div class="flex justify-between"><dt class="text-gray-500">IGST</dt><dd>{{ \App\Support\Money::format($t['igst_total']) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-gray-500">Round off</dt><dd>{{ \App\Support\Money::format($t['round_off']) }}</dd></div>
                <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900"><dt>Total</dt><dd>{{ \App\Support\Money::format($t['total']) }}</dd></div>
            </dl>
            <button wire:click="save" type="button" class="mt-6 w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                Save quotation
            </button>
        </div>
    </div>
</div>
