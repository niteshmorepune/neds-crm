<x-app-layout>
    <x-slot name="header">Billing Settings</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        {{-- Billing default --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Billing default</h2>
            <form method="POST" action="{{ route('billing-settings.sac-default.update') }}" class="mt-3 flex flex-wrap items-end gap-3">
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
                The CRM would currently assign <strong>{{ $nextDomesticPreview }}</strong> next for an Indian client,
                <strong>{{ $nextExportPreview }}</strong> next for an Out of India client, and
                <strong>{{ $nextNonGstPreview }}</strong> next for a Non-GST invoice. Use this to catch the CRM's
                counter up to Hitech's real numbering — set it to the number Hitech would assign next, not the last one used.
            </p>
            <form method="POST" action="{{ route('billing-settings.invoice-numbering.update') }}" class="mt-3 flex flex-wrap items-end gap-3">
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
                <div class="w-52">
                    <x-input-label for="next_non_gst_number" value="Next number — Non-GST invoices" />
                    <x-text-input id="next_non_gst_number" name="next_non_gst_number" type="number" min="1" class="mt-1 block w-full" :value="old('next_non_gst_number', $nextNonGstNumber)" required />
                    <x-input-error :messages="$errors->get('next_non_gst_number')" class="mt-1" />
                </div>
                <x-primary-button>Save</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
