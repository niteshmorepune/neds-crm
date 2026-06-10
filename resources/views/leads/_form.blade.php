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
        <x-input-label for="status" value="Status *" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $lead->status?->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="next_follow_up_at" value="Next follow-up" />
        <x-text-input id="next_follow_up_at" name="next_follow_up_at" type="datetime-local" class="mt-1 block w-full" :value="$followUp" />
        <x-input-error :messages="$errors->get('next_follow_up_at')" class="mt-1" />
    </div>
</div>
