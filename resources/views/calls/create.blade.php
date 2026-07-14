<x-app-layout>
    <x-slot name="header">Log a Call</x-slot>

    <div class="max-w-2xl mx-auto">
        <form method="POST" action="{{ route('calls.store') }}"
              class="rounded-lg bg-white p-6 shadow-sm grid grid-cols-1 gap-4 md:grid-cols-2"
              x-data="{
                  outcome: '{{ old('outcome', '') }}',
                  showFollowUp: {{ old('follow_up_at') ? 'true' : 'false' }},
              }"
              x-init="$watch('outcome', val => {
                  if (['no_answer','busy','follow_up_needed'].includes(val)) showFollowUp = true;
              })">
            @csrf
            <div>
                <x-input-label for="customer_id" value="Client" />
                <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">—</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_id', $selectedCustomer) === $customer->id)>{{ $customer->company_name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($leads->isNotEmpty())
                <div>
                    <x-input-label for="lead_id" value="…or Lead" />
                    <select id="lead_id" name="lead_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">—</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}" @selected((int) old('lead_id', $selectedLead) === $lead->id)>{{ $lead->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <x-input-label for="direction" value="Direction *" />
                <select id="direction" name="direction" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @foreach ($directions as $d)<option value="{{ $d->value }}" @selected(old('direction') === $d->value)>{{ $d->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-input-label for="outcome" value="Outcome *" />
                <select id="outcome" name="outcome" x-model="outcome" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @foreach ($outcomes as $o)<option value="{{ $o->value }}" @selected(old('outcome') === $o->value)>{{ $o->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-input-label for="duration_minutes" value="Duration (mins)" />
                <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="0" class="mt-1 block w-full" :value="old('duration_minutes')" />
            </div>
            <div>
                <x-input-label for="called_at" value="When *" />
                <x-text-input id="called_at" name="called_at" type="datetime-local" class="mt-1 block w-full"
                    :value="old('called_at', now()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i'))" />
                <x-input-error :messages="$errors->get('called_at')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes') }}</textarea>
            </div>

            {{-- Follow-up reminder --}}
            <div class="md:col-span-2 border-t border-gray-100 pt-4">
                <button type="button" @click="showFollowUp = !showFollowUp"
                        class="flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    <span x-text="showFollowUp ? '▼' : '▶'"></span>
                    <span x-text="showFollowUp ? 'Follow-up reminder' : 'Add follow-up reminder'"></span>
                </button>

                <div x-show="showFollowUp" x-transition class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="follow_up_at" value="Remind me on" />
                        <x-text-input id="follow_up_at" name="follow_up_at" type="datetime-local" class="mt-1 block w-full"
                            :value="old('follow_up_at')" />
                        <x-input-error :messages="$errors->get('follow_up_at')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="next_action" value="Next action" />
                        <x-text-input id="next_action" name="next_action" type="text" class="mt-1 block w-full"
                            placeholder="e.g. Send proposal, Call back at 3 PM"
                            :value="old('next_action')" />
                        <x-input-error :messages="$errors->get('next_action')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="md:col-span-2 flex items-center justify-end gap-3">
                <a href="{{ route('calls.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Log Call</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
