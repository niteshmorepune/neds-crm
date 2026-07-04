<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Import Attendance from Hitech</h1>
        <a href="{{ route('attendance.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back to attendance</a>
    </div>

    {{-- Step 1: pick staff + upload --}}
    @if ($step === 1)
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-600">
                The biometric device sometimes loses a punch before it reaches the CRM (its stored
                log gets cleared by other software polling the same terminal). Hitech reads the same
                device and keeps its own complete record per staff member — use this to correct the
                CRM whenever the two disagree.
            </p>
            <p class="mt-2 text-sm text-gray-500">
                In Hitech: open the staff member's <strong>Attendance</strong> tab, pick a date range,
                then <strong>Export To Excel</strong>. Upload that file below.
            </p>

            <div class="mt-4">
                <x-input-label value="Staff member" />
                <select wire:model="userId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">— select —</option>
                    @foreach ($this->users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                @error('userId') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-4">
                <x-input-label value="Hitech export (.xlsx)" />
                <input type="file" wire:model.live="file" accept=".xlsx"
                       class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700" />
                @error('file') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
            </div>
            <div wire:loading wire:target="file" class="mt-2 text-sm text-indigo-600">Uploading file…</div>

            <div class="mt-4">
                <x-primary-button wire:click="parse" wire:loading.attr="disabled" wire:target="parse" type="button">
                    <span wire:loading.remove wire:target="parse">Continue</span>
                    <span wire:loading wire:target="parse">Processing…</span>
                </x-primary-button>
            </div>
        </div>
    @endif

    {{-- Step 2: preview + confirm --}}
    @if ($step === 2)
        <form wire:submit="import" class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Review before importing</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ count($preview) }} day(s) found. Only the entry/exit fields Hitech actually reports
                will be written — a blank cell never erases a value already in the CRM.
            </p>

            <div class="mt-4 overflow-x-auto rounded-md border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Hitech In</th>
                            <th class="px-3 py-2">Hitech Out</th>
                            <th class="px-3 py-2">Current In (CRM)</th>
                            <th class="px-3 py-2">Current Out (CRM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($preview as $row)
                            @php
                                $inChanges = $row['entry'] && $row['entry'] !== $row['current_in'];
                                $outChanges = $row['exit'] && $row['exit'] !== $row['current_out'];
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-700">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M (D)') }}</td>
                                <td class="px-3 py-2 {{ $inChanges ? 'font-medium text-indigo-700' : 'text-gray-600' }}">{{ $row['entry'] ?? '—' }}</td>
                                <td class="px-3 py-2 {{ $outChanges ? 'font-medium text-indigo-700' : 'text-gray-600' }}">{{ $row['exit'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $row['current_in'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $row['current_out'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="import">
                    <span wire:loading.remove wire:target="import">Import {{ count($preview) }} day(s)</span>
                    <span wire:loading wire:target="import">Importing…</span>
                </x-primary-button>
                <button wire:click="startOver" type="button" class="text-sm text-gray-500 hover:text-gray-700">Start over</button>
            </div>
        </form>
    @endif
</div>
