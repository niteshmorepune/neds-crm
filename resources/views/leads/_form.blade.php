@php
    $valueRupees = old('estimated_value', $lead->estimated_value !== null ? $lead->estimated_value / 100 : null);
    $followUp = old('next_follow_up_at', $lead->next_follow_up_at?->format('Y-m-d\TH:i'));
@endphp

<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    <div>
        <x-input-label for="name" value="Contact name *" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $lead->name)" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="company" value="Company" />
        <x-text-input id="company" name="company" type="text" class="mt-1 block w-full" :value="old('company', $lead->company)" />
    </div>

    <div>
        <x-input-label for="email" value="Email" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $lead->email)" />
        <x-input-error :messages="$errors->get('email')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="phone" value="Phone" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $lead->phone)" />
    </div>

    <div>
        <x-input-label for="alternate_phone" value="Alternate phone" />
        <x-text-input id="alternate_phone" name="alternate_phone" type="text" class="mt-1 block w-full" :value="old('alternate_phone', $lead->alternate_phone)" />
        <x-input-error :messages="$errors->get('alternate_phone')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="source" value="Source *" />
        <select id="source" name="source" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($sources as $source)
                <option value="{{ $source->value }}" @selected(old('source', $lead->source?->value) === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('source')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="service_id" value="Service interested in" />
        <select id="service_id" name="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">—</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected((string) old('service_id', $lead->service_id) === (string) $service->id)>{{ $service->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="estimated_value" value="Estimated value (₹)" />
        <x-text-input id="estimated_value" name="estimated_value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$valueRupees" />
        <x-input-error :messages="$errors->get('estimated_value')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="owner_id" value="Owner" />
        <select id="owner_id" name="owner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Unassigned</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((string) old('owner_id', $lead->owner_id) === (string) $owner->id)>{{ $owner->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="telecaller_id" value="Telecaller" />
        <select id="telecaller_id" name="telecaller_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Unassigned</option>
            @foreach ($telecallers as $telecaller)
                <option value="{{ $telecaller->id }}" @selected((string) old('telecaller_id', $lead->telecaller_id) === (string) $telecaller->id)>{{ $telecaller->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-400">Who calls this lead — separate from Owner. A telecaller only sees leads assigned to them here.</p>
    </div>

    <div>
        <x-input-label for="status" value="Status *" />
        @if ($lead->status === \App\Enums\LeadStatus::Converted)
            {{-- No <select> at all, on purpose — this lead already became a
                 real Deal + Client via the Convert action, and status is not
                 a legal value in the New/Contacted/Qualified/Lost dropdown
                 below, so it must never be resubmitted through this form
                 (see LeadUpdateRequest::rules()). --}}
            <div class="mt-1">
                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Converted</span>
                <p class="mt-1 text-xs text-gray-400">This lead became a client — status can't be changed here. See the linked Client/Deal on the lead's page.</p>
            </div>
        @else
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $lead->status?->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-400">Qualified = real budget & need confirmed, not just contacted. Converted = this lead became a real Deal + Client.</p>
        @endif
        <x-input-error :messages="$errors->get('status')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="next_follow_up_at" value="Next follow-up" />
        <x-text-input id="next_follow_up_at" name="next_follow_up_at" type="datetime-local" class="mt-1 block w-full" :value="$followUp" />
        <x-input-error :messages="$errors->get('next_follow_up_at')" class="mt-1" />
    </div>
</div>
