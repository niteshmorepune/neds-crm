<x-app-layout>
    <x-slot name="header">New Ticket</x-slot>

    <div class="max-w-3xl mx-auto">
        <form method="POST" action="{{ route('tickets.store') }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-input-label for="subject" value="Subject *" />
                    <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" :value="old('subject')" required />
                    <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="customer_id" value="Client *" />
                    <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select client</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->company_name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="service_id" value="Service" />
                    <select id="service_id" name="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">—</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((string) old('service_id') === (string) $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="priority" value="Priority *" />
                    <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="assignee_id" value="Assignee" />
                    <select id="assignee_id" name="assignee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $person)
                            <option value="{{ $person->id }}" @selected((string) old('assignee_id') === (string) $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <x-input-label for="description" value="Description *" />
                    <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>{{ old('description') }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Create Ticket</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
