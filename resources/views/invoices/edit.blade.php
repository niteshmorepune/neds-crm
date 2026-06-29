<x-app-layout>
    <x-slot name="header">Edit Invoice {{ $invoice->invoice_number }}</x-slot>

    <div class="max-w-2xl mx-auto">
        <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div>
                <x-input-label for="invoice_number" value="Hitech Invoice Number *" />
                <x-text-input id="invoice_number" name="invoice_number" type="text" class="mt-1 block w-full"
                              :value="old('invoice_number', $invoice->invoice_number)" required />
                <x-input-error :messages="$errors->get('invoice_number')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="customer_id" value="Client *" />
                <select id="customer_id" name="customer_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        x-data x-on:change="
                            let cid = $event.target.value;
                            document.querySelectorAll('#deal_id option, #project_id option').forEach(o => {
                                o.hidden = o.value !== '' && o.dataset.customer !== cid;
                            });
                            document.getElementById('deal_id').value = '';
                            document.getElementById('project_id').value = '';
                        ">
                    <option value="">— Select client —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $invoice->customer_id) == $customer->id)>{{ $customer->company_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="deal_id" value="Deal (optional)" />
                    <select id="deal_id" name="deal_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        @foreach ($deals as $deal)
                            <option value="{{ $deal->id }}" data-customer="{{ $deal->customer_id }}"
                                    @selected(old('deal_id', $invoice->deal_id) == $deal->id)>{{ $deal->title }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('deal_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="project_id" value="Project (optional)" />
                    <select id="project_id" name="project_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" data-customer="{{ $project->customer_id }}"
                                    @selected(old('project_id', $invoice->project_id) == $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('project_id')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="issue_date" value="Invoice Date *" />
                    <x-text-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                                  :value="old('issue_date', $invoice->issue_date->toDateString())" required />
                    <x-input-error :messages="$errors->get('issue_date')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="due_date" value="Due Date" />
                    <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full"
                                  :value="old('due_date', $invoice->due_date?->toDateString())" />
                    <x-input-error :messages="$errors->get('due_date')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="amount" value="Amount (₹) *" />
                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                              class="mt-1 block w-full"
                              :value="old('amount', \App\Support\Money::toRupees($invoice->total))" required />
                <x-input-error :messages="$errors->get('amount')" class="mt-1" />
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button>Save Changes</x-primary-button>
                <a href="{{ route('invoices.show', $invoice) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
