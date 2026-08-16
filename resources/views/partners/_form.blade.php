<div>
    <x-input-label for="name" value="Agency / Partner name *" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $partner->name ?? '')" required />
    <x-input-error :messages="$errors->get('name')" class="mt-1" />
</div>

<div>
    <x-input-label for="email" value="Email" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $partner->email ?? '')" />
    <x-input-error :messages="$errors->get('email')" class="mt-1" />
</div>

<div>
    <x-input-label for="phone" value="Phone / WhatsApp" />
    <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $partner->phone ?? '')" />
    <x-input-error :messages="$errors->get('phone')" class="mt-1" />
</div>

<div>
    <x-input-label for="commission_rate" value="Commission rate (%)" />
    <x-text-input id="commission_rate" name="commission_rate" type="number" step="0.01" min="0" max="100"
                  class="mt-1 block w-full" :value="old('commission_rate', $partner->commission_rate ?? '')" />
    <p class="mt-1 text-xs text-gray-500">Percentage of a referred deal's value (before tax), paid when the deal is marked Won. Leave blank if this partner isn't on a commission arrangement.</p>
    <x-input-error :messages="$errors->get('commission_rate')" class="mt-1" />
</div>

<div>
    <x-input-label for="billing_customer_id" value="Bills as reseller for" />
    <select id="billing_customer_id" name="billing_customer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
        <option value="">Not a reseller — bill each referred client directly</option>
        @foreach ($customers as $customer)
            <option value="{{ $customer->id }}" @selected(old('billing_customer_id', $partner->billing_customer_id ?? '') == $customer->id)>{{ $customer->company_name }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">If set, every quotation/invoice/recurring invoice for a client referred by this partner is billed to this customer instead — e.g. Brand-Whiz's own clients get invoiced to Brand Whiz, not each client directly.</p>
    <x-input-error :messages="$errors->get('billing_customer_id')" class="mt-1" />
</div>

<div>
    <x-input-label for="notes" value="Notes" />
    <textarea id="notes" name="notes" rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $partner->notes ?? '') }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
</div>
