<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Import Clients</h1>
        <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back to clients</a>
    </div>

    {{-- Step 1: upload --}}
    @if ($step === 1)
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-600">
                Upload a CSV file (first row = column headers). You'll map columns to fields on the next step.
                Rows with a duplicate email or GSTIN (already in the system or repeated in the file) are skipped.
            </p>
            <p class="mt-2 text-sm text-gray-500">
                Not sure of the format?
                <a href="{{ route('clients.import.template') }}" class="text-indigo-600 hover:underline font-medium">Download the CSV template</a>
                to see required columns and a sample row.
            </p>
            <div class="mt-4">
                <input type="file" wire:model="file" accept=".csv,.txt"
                       class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700" />
                @error('file') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="mt-4">
                <x-primary-button wire:click="parse" wire:loading.attr="disabled" wire:target="parse,file">
                    <span wire:loading.remove wire:target="file">Continue</span>
                    <span wire:loading wire:target="file">Uploading…</span>
                </x-primary-button>
            </div>
        </div>
    @endif

    {{-- Step 2: map columns --}}
    @if ($step === 2)
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Map columns</h2>
            <p class="mt-1 text-sm text-gray-500">{{ count($rows) }} data row(s) found.</p>
            @error('mapping') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($this->fields() as $field => $label)
                    <div>
                        <x-input-label :value="$label" />
                        <select wire:model="mapping.{{ $field }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">— skip —</option>
                            @foreach ($headers as $index => $header)
                                <option value="{{ $index }}">{{ $header }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button wire:click="import" wire:loading.attr="disabled" wire:target="import">
                    Import {{ count($rows) }} row(s)
                </x-primary-button>
                <button wire:click="startOver" type="button" class="text-sm text-gray-500 hover:text-gray-700">Start over</button>
            </div>
        </div>
    @endif

    {{-- Step 3: results --}}
    @if ($step === 3)
        <div class="space-y-4">
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                Imported <strong>{{ $results['imported'] }}</strong> client(s).
                Skipped {{ count($results['skipped']) }} duplicate(s), {{ count($results['errors']) }} error(s).
            </div>

            @if (! empty($results['errors']))
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-red-700">Errors</h3>
                    <ul class="mt-2 space-y-1 text-sm text-gray-600">
                        @foreach ($results['errors'] as $error)
                            <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($results['skipped']))
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-amber-700">Skipped (duplicates)</h3>
                    <ul class="mt-2 space-y-1 text-sm text-gray-600">
                        @foreach ($results['skipped'] as $skip)
                            <li>Row {{ $skip['row'] }}: {{ $skip['reason'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex items-center gap-3">
                <a href="{{ route('clients.index') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">View clients</a>
                <button wire:click="startOver" type="button" class="text-sm text-gray-500 hover:text-gray-700">Import another file</button>
            </div>
        </div>
    @endif
</div>
