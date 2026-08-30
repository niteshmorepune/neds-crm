<x-app-layout>
    <x-slot name="header">Services</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        {{-- Add a service --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Add a service line</h2>
            <form method="POST" action="{{ route('services.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1 min-w-48">
                    <x-input-label for="name" value="Name *" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div class="w-28">
                    <x-input-label for="sort_order" value="Sort" />
                    <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order')" />
                </div>
                <x-primary-button>Add</x-primary-button>
            </form>
        </div>

        {{-- Billing default --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Billing default</h2>
            <form method="POST" action="{{ route('services.billing-settings.update') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                @method('PATCH')
                <div class="w-40">
                    <x-input-label for="default_sac_code" value="Default SAC/HSN code" />
                    <x-text-input id="default_sac_code" name="default_sac_code" type="text" class="mt-1 block w-full" :value="old('default_sac_code', $defaultSacCode)" required />
                    <x-input-error :messages="$errors->get('default_sac_code')" class="mt-1" />
                </div>
                <x-primary-button>Save</x-primary-button>
            </form>
            <p class="mt-2 text-xs text-gray-400">Pre-fills the SAC/HSN field on every new Quotation, Invoice, and Recurring Invoice line item. Still freely editable per line.</p>
        </div>

        {{-- Invoice numbering --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Invoice numbering</h2>
            <p class="mt-1 text-xs text-gray-400">
                The CRM would currently assign <strong>{{ $nextDomesticPreview }}</strong> next for an Indian client and
                <strong>{{ $nextExportPreview }}</strong> next for an Out of India client. Use this to catch the CRM's
                counter up to Hitech's real numbering — set it to the number Hitech would assign next, not the last one used.
            </p>
            <form method="POST" action="{{ route('services.invoice-numbering.update') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                @method('PATCH')
                <div class="w-28">
                    <x-input-label for="financial_year" value="Financial year" />
                    <x-text-input id="financial_year" name="financial_year" type="text" class="mt-1 block w-full" :value="old('financial_year', $currentFy)" required />
                    <x-input-error :messages="$errors->get('financial_year')" class="mt-1" />
                </div>
                <div class="w-52">
                    <x-input-label for="next_domestic_number" value="Next number — Indian clients" />
                    <x-text-input id="next_domestic_number" name="next_domestic_number" type="number" min="1" class="mt-1 block w-full" :value="old('next_domestic_number', $nextDomesticNumber)" required />
                    <x-input-error :messages="$errors->get('next_domestic_number')" class="mt-1" />
                </div>
                <div class="w-52">
                    <x-input-label for="next_export_number" value="Next number — Out of India clients" />
                    <x-text-input id="next_export_number" name="next_export_number" type="number" min="1" class="mt-1 block w-full" :value="old('next_export_number', $nextExportNumber)" required />
                    <x-input-error :messages="$errors->get('next_export_number')" class="mt-1" />
                </div>
                <x-primary-button>Save</x-primary-button>
            </form>
        </div>

        {{-- Existing services (inline edit; row inputs bind to the forms below via form="…") --}}
        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3 w-24">Sort</th>
                        <th class="px-4 py-3 w-28">Active</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($services as $service)
                        <tr>
                            <td class="px-4 py-3">
                                <input form="svc-update-{{ $service->id }}" name="name" type="text" value="{{ $service->name }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" required />
                            </td>
                            <td class="px-4 py-3">
                                <input form="svc-update-{{ $service->id }}" name="sort_order" type="number" min="0" value="{{ $service->sort_order }}" class="block w-20 rounded-md border-gray-300 text-sm shadow-sm" />
                            </td>
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-2">
                                    <input form="svc-update-{{ $service->id }}" type="checkbox" name="is_active" value="1" @checked($service->is_active) class="rounded border-gray-300 text-indigo-600" />
                                    <span class="text-xs text-gray-500">{{ $service->is_active ? 'Active' : 'Off' }}</span>
                                </label>
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <button form="svc-update-{{ $service->id }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Save</button>
                                <button form="svc-delete-{{ $service->id }}" class="text-xs text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No services yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400">Services in use by leads, deals, projects or tickets can't be deleted — deactivate them instead.</p>

        {{-- One update + delete form per row, referenced by the inputs above. --}}
        @foreach ($services as $service)
            <form id="svc-update-{{ $service->id }}" method="POST" action="{{ route('services.update', $service) }}" class="hidden">@csrf @method('PUT')</form>
            <form id="svc-delete-{{ $service->id }}" method="POST" action="{{ route('services.destroy', $service) }}" class="hidden" onsubmit="return confirm('Remove {{ $service->name }}?')">@csrf @method('DELETE')</form>
        @endforeach
    </div>
</x-app-layout>
