<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900">Milestone billing</h2>

    <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
        <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Billed</div><div class="font-semibold">{{ \App\Support\Money::format($billed) }}</div></div>
        <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Collected</div><div class="font-semibold">{{ \App\Support\Money::format($collected) }}</div></div>
        <div class="rounded-md bg-gray-50 p-3"><div class="text-gray-500">Remaining</div><div class="font-semibold">{{ \App\Support\Money::format($remaining) }}</div></div>
    </div>

    <ul class="mt-4 divide-y divide-gray-100 text-sm">
        @forelse ($milestones as $m)
            <li class="flex items-center justify-between py-2">
                <div>
                    <span class="font-medium text-gray-900">{{ $m->title }}</span>
                    <span class="text-gray-500">· {{ rtrim(rtrim($m->percentage, '0'), '.') }}% · {{ \App\Support\Money::format($m->amount) }}</span>
                    @if ($m->due_date)<span class="text-gray-400"> · due {{ $m->due_date->format('d M Y') }}</span>@endif
                    @if (! $m->isBilled() && $m->readyToInvoice())
                        <span class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Ready to invoice</span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if (! $m->isBilled() && $canManage)
                        <select wire:change="updateStatus({{ $m->id }}, $event.target.value)" class="rounded-md border-gray-300 text-xs shadow-sm">
                            @foreach (\App\Enums\MilestoneStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($m->status === $status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    @elseif (! $m->isBilled())
                        <span class="text-xs text-gray-500">{{ $m->status->label() }}</span>
                    @endif
                    @if ($m->isBilled())
                        <a href="{{ route('invoices.show', $m->invoice) }}" class="text-indigo-600 hover:underline">{{ $m->invoice->invoice_number }}</a>
                    @elseif ($canManage)
                        <button wire:click="generate({{ $m->id }})" class="text-green-600 hover:text-green-500">Generate invoice</button>
                        <button wire:click="removeMilestone({{ $m->id }})" wire:confirm="Remove milestone?" class="text-red-600 hover:text-red-500">Remove</button>
                    @endif
                </div>
            </li>
        @empty
            <li class="py-2 text-gray-400">No milestones defined.</li>
        @endforelse
    </ul>

    @if ($canManage)
        <div class="mt-4 grid grid-cols-12 gap-2 border-t border-gray-100 pt-4">
            <div class="col-span-5">
                <input wire:model="title" placeholder="Title (e.g. Advance)" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="col-span-3">
                <input wire:model="percentage" type="number" step="0.01" placeholder="%" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                @error('percentage') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="col-span-3">
                <input wire:model="due_date" type="date" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" />
            </div>
            <button wire:click="addMilestone" type="button" class="col-span-1 rounded-md bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-500">Add</button>
        </div>
        <p class="mt-1 text-xs text-gray-400">{{ $this->usedPercentage() }}% of 100% allocated.</p>
    @endif
</div>
