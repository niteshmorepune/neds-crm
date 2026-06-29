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
    <x-input-label for="notes" value="Notes" />
    <textarea id="notes" name="notes" rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $partner->notes ?? '') }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
</div>
