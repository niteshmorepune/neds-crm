@php($costRupees = old('cost', isset($subscription) ? \App\Support\Money::toRupees($subscription->cost) : ''))

<div>
    <x-input-label for="name" value="Subscription name *" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $subscription->name ?? '')" required />
    <x-input-error :messages="$errors->get('name')" class="mt-1" />
</div>

<div>
    <x-input-label for="vendor" value="Vendor" />
    <x-text-input id="vendor" name="vendor" type="text" class="mt-1 block w-full" :value="old('vendor', $subscription->vendor ?? '')" />
    <x-input-error :messages="$errors->get('vendor')" class="mt-1" />
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="cost" value="Cost per cycle (₹) *" />
        <x-text-input id="cost" name="cost" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$costRupees" required />
        <x-input-error :messages="$errors->get('cost')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="billing_cycle" value="Billing cycle *" />
        <select id="billing_cycle" name="billing_cycle" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            @foreach (\App\Enums\RecurringFrequency::cases() as $option)
                <option value="{{ $option->value }}" @selected(old('billing_cycle', $subscription->billing_cycle->value ?? 'monthly') === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('billing_cycle')" class="mt-1" />
    </div>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="renewal_date" value="Next renewal date *" />
        <x-text-input id="renewal_date" name="renewal_date" type="date" class="mt-1 block w-full"
            :value="old('renewal_date', isset($subscription) ? $subscription->renewal_date->toDateString() : '')" required />
        <x-input-error :messages="$errors->get('renewal_date')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="reminder_days_before" value="Remind me this many days before *" />
        <x-text-input id="reminder_days_before" name="reminder_days_before" type="number" min="1" max="90" class="mt-1 block w-full"
            :value="old('reminder_days_before', $subscription->reminder_days_before ?? 7)" required />
        <p class="mt-1 text-xs text-gray-400">Keep this shorter than the billing cycle, or the reminder can repeat every day.</p>
        <x-input-error :messages="$errors->get('reminder_days_before')" class="mt-1" />
    </div>
</div>

<div>
    <x-input-label for="notes" value="Notes" />
    <textarea id="notes" name="notes" rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $subscription->notes ?? '') }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
</div>

<div>
    <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $subscription->is_active ?? true)) class="rounded border-gray-300 text-indigo-600" />
        <span class="text-sm text-gray-700">Active (uncheck to stop reminders without deleting)</span>
    </label>
</div>
