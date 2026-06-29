<x-app-layout>
    <x-slot name="header">Import Invoices from CSV</x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('invoices.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="csv" value="CSV File" />
                    <input id="csv" name="csv" type="file" accept=".csv,.txt"
                           class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" />
                    <x-input-error :messages="$errors->get('csv')" class="mt-1" />
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <x-primary-button>Import</x-primary-button>
                    <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </form>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-5 text-sm text-gray-600">
            <p class="font-medium text-gray-800">Expected CSV format</p>
            <p class="mt-1">Save your Hitech export as CSV and ensure these column headers are present (names are flexible — the importer recognises common Hitech variants):</p>
            <ul class="mt-2 list-disc pl-5 space-y-1">
                <li><strong>Invoice No</strong> — Hitech invoice number (must be unique)</li>
                <li><strong>Date</strong> — invoice date in DD/MM/YYYY format</li>
                <li><strong>Client Name</strong> — must match the company name in CRM exactly</li>
                <li><strong>Amount</strong> — total amount in rupees (numbers only, e.g. 50000)</li>
                <li><strong>Due Date</strong> (optional) — DD/MM/YYYY</li>
            </ul>
            <p class="mt-3">Rows with an unrecognised client or already-existing invoice number are skipped and reported. All other rows are imported.</p>
            <div class="mt-3 rounded bg-white border border-gray-200 px-3 py-2 font-mono text-xs text-gray-500 overflow-x-auto whitespace-pre">Invoice No,Date,Client Name,Amount,Due Date
HT-2026-0042,01/07/2026,Acme Corp,50000,31/07/2026
HT-2026-0043,05/07/2026,Beta Solutions,118000,</div>
        </div>
    </div>
</x-app-layout>
