@php
    $tagsValue = old('tags', $customer->tags ?? []);
    $tagsValue = is_array($tagsValue) ? implode(', ', $tagsValue) : $tagsValue;
@endphp

@php
    $countryValue = old('country', $customer->country ?? 'India');
    $isOverseasInit = ! empty($countryValue) && strtolower(trim($countryValue)) !== 'india';
@endphp

<div class="grid grid-cols-1 gap-6 md:grid-cols-2"
     x-data="{ country: {{ Js::from($countryValue) }}, get isOverseas() { return this.country.trim().toLowerCase() !== 'india' && this.country.trim() !== '' } }">
    <div class="md:col-span-2">
        <x-input-label for="company_name" value="Company name *" />
        <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full"
                      :value="old('company_name', $customer->company_name)" required autofocus />
        <x-input-error :messages="$errors->get('company_name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="country" value="Country *" />
        <x-text-input id="country" name="country" type="text" class="mt-1 block w-full"
                      x-model="country"
                      :value="old('country', $customer->country ?? 'India')"
                      placeholder="India" required />
        <p class="mt-1 text-xs text-gray-400">Set to anything other than "India" to disable GST on invoices.</p>
        <x-input-error :messages="$errors->get('country')" class="mt-1" />
    </div>

    <div x-show="!isOverseas">
        <x-input-label for="gstin" value="GSTIN" />
        <x-text-input id="gstin" name="gstin" type="text" maxlength="15" class="mt-1 block w-full uppercase"
                      :value="old('gstin', $customer->gstin)" placeholder="27ABCDE1234F1Z5" />
        <x-input-error :messages="$errors->get('gstin')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="status" value="Status *" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $customer->status?->value) === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="email" value="Email" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                      :value="old('email', $customer->email)" />
        <x-input-error :messages="$errors->get('email')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="phone" value="Phone" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                      :value="old('phone', $customer->phone)" />
        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="website" value="Website" />
        <x-text-input id="website" name="website" type="url" class="mt-1 block w-full"
                      :value="old('website', $customer->website)" placeholder="https://example.com" />
        <x-input-error :messages="$errors->get('website')" class="mt-1" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="address_line1" value="Address line 1" />
        <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full"
                      :value="old('address_line1', $customer->address_line1)" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="address_line2" value="Address line 2" />
        <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full"
                      :value="old('address_line2', $customer->address_line2)" />
    </div>

    <div>
        <x-input-label for="city" value="City" />
        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full"
                      :value="old('city', $customer->city)" />
    </div>

    <div x-show="!isOverseas">
        <x-input-label for="state_code" value="State" />
        <select id="state_code" name="state_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Select state</option>
            @foreach ($states as $code => $name)
                <option value="{{ $code }}" @selected(old('state_code', $customer->state_code) === $code)>
                    {{ $name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('state_code')" class="mt-1" />
    </div>

    <div x-show="!isOverseas">
        <x-input-label for="pincode" value="Pincode" />
        <x-text-input id="pincode" name="pincode" type="text" class="mt-1 block w-full"
                      :value="old('pincode', $customer->pincode)" />
    </div>

    <div>
        <x-input-label for="owner_id" value="Owner" />
        <select id="owner_id" name="owner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Unassigned</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((string) old('owner_id', $customer->owner_id) === (string) $owner->id)>
                    {{ $owner->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('owner_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="referring_partner_id" value="Referred by (agency)" />
        <select id="referring_partner_id" name="referring_partner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">—</option>
            @foreach ($partners as $partner)
                <option value="{{ $partner->id }}" @selected((string) old('referring_partner_id', $customer->referring_partner_id) === (string) $partner->id)>
                    {{ $partner->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('referring_partner_id')" class="mt-1" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="tags" value="Tags (comma-separated)" />
        <x-text-input id="tags" name="tags" type="text" class="mt-1 block w-full"
                      :value="$tagsValue" placeholder="retainer, seo, priority" />
        <x-input-error :messages="$errors->get('tags')" class="mt-1" />
    </div>
</div>
