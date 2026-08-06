<div>
    <div class="flex items-center justify-between">
        @if ($customer)
            <h2 class="text-base font-semibold text-gray-900">Links</h2>
        @endif
        @if ($canManage && ! $showForm)
            <button wire:click="newLink"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                Add link
            </button>
        @endif
    </div>

    @if ($showForm)
        <div class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-gray-200 p-4 md:grid-cols-2">
            <div>
                <x-input-label value="Label *" />
                <x-text-input wire:model="label" type="text" class="mt-1 block w-full" placeholder="e.g. Google Business Profile" />
                @error('label') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="URL *" />
                <x-text-input wire:model="url" type="url" class="mt-1 block w-full" placeholder="https://…" />
                @error('url') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Department" />
                <select wire:model="department" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Uncategorized</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->value }}">{{ $dept->label() }}</option>
                    @endforeach
                </select>
                @error('department') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Purpose" />
                <select wire:model="purpose" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Uncategorized</option>
                    @foreach ($purposes as $p)
                        <option value="{{ $p->value }}">{{ $p->label() }}</option>
                    @endforeach
                </select>
                @error('purpose') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center gap-3 md:col-span-2">
                <x-primary-button wire:click="save" type="button">Save link</x-primary-button>
                <button wire:click="cancel" type="button" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <select wire:model.live="filterDepartment" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All departments</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->value }}">{{ $dept->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterPurpose" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All purposes</option>
            @foreach ($purposes as $p)
                <option value="{{ $p->value }}">{{ $p->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-2">
        @forelse ($groupedLinks as $departmentKey => $group)
            <div class="mt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    {{ $departmentKey === '' ? 'Uncategorized' : \App\Enums\LinkDepartment::from($departmentKey)->label() }}
                </h3>
                <div class="divide-y divide-gray-100">
                    @foreach ($group as $link)
                        <div class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                                   class="font-medium text-indigo-600 hover:underline">
                                    {{ $link->label }}
                                </a>
                                @if ($link->purpose)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ $link->purpose->label() }}</span>
                                @endif
                            </div>
                            @if ($canManage)
                                <div class="flex items-center gap-3 text-sm">
                                    <button wire:click="edit({{ $link->id }})" class="text-gray-500 hover:text-gray-700">Edit</button>
                                    <button wire:click="delete({{ $link->id }})"
                                            wire:confirm="Delete this link?"
                                            class="text-red-600 hover:text-red-500">Delete</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="py-3 text-sm text-gray-400">No links added yet.</p>
        @endforelse
    </div>
</div>
