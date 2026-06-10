<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">{{ $recurringId ? 'Edit' : 'New' }} Recurring Invoice</h1>
        <a href="{{ route('recurring-invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
    </div>

    <div class="rounded-lg bg-white p-6 shadow-sm grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <x-input-label value="Client *" />
            <select wire:model="customer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">Select client</option>
                @foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->company_name }}</option>@endforeach
            </select>
            @error('customer_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <x-input-label value="Service" />
            <select wire:model="service_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">—</option>
                @foreach ($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <x-input-label value="Frequency *" />
            <select wire:model="frequency" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                @foreach ($frequencies as $f)<option value="{{ $f->value }}">{{ $f->label() }}</option>@endforeach
            </select>
        </div>
        <div>
            <x-input-label value="Start date *" />
            <x-text-input wire:model="start_date" type="date" class="mt-1 block w-full" />
            @error('start_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <x-input-label value="End date" />
            <x-text-input wire:model="end_date" type="date" class="mt-1 block w-full" />
            @error('end_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <x-input-label value="Discount (₹)" />
            <x-text-input wire:model="discount" type="number" step="0.01" min="0" class="mt-1 block w-full" />
        </div>
    </div>

    <div class="rounded-lg bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Line items</h2>
            <button wire:click="addItem" type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">+ Add item</button>
        </div>
        @error('items') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

        <div class="mt-4 space-y-3">
            @foreach ($items as $i => $item)
                <div class="grid grid-cols-12 gap-2 border-b border-gray-100 pb-3" wire:key="ri-{{ $i }}">
                    <input wire:model="items.{{ $i }}.description" placeholder="Description" class="col-span-12 md:col-span-5 rounded-md border-gray-300 text-sm shadow-sm" />
                    <input wire:model="items.{{ $i }}.sac_code" placeholder="SAC" class="col-span-4 md:col-span-2 rounded-md border-gray-300 text-sm shadow-sm" />
                    <input wire:model="items.{{ $i }}.quantity" type="number" step="0.01" placeholder="Qty" class="col-span-2 md:col-span-1 rounded-md border-gray-300 text-sm shadow-sm" />
                    <input wire:model="items.{{ $i }}.rate" type="number" step="0.01" placeholder="Rate ₹" class="col-span-3 md:col-span-2 rounded-md border-gray-300 text-sm shadow-sm" />
                    <input wire:model="items.{{ $i }}.gst_rate" type="number" step="0.01" placeholder="GST%" class="col-span-2 md:col-span-1 rounded-md border-gray-300 text-sm shadow-sm" />
                    <button wire:click="removeItem({{ $i }})" type="button" class="col-span-1 text-red-600 hover:text-red-500">&times;</button>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            <x-input-label value="Terms" />
            <textarea wire:model="terms" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
        </div>

        <button wire:click="save" type="button" class="mt-6 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Save</button>
    </div>
</div>
