<div>
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Service Links</h3>
        @if ($canManage && ! $showForm)
            <button wire:click="newLink" type="button"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                Add link
            </button>
        @endif
    </div>

    @if ($showForm)
        <div class="mt-3 grid grid-cols-1 gap-3 rounded-md border border-gray-200 p-4 md:grid-cols-3">
            <div>
                <x-input-label value="Service *" />
                <select wire:model="serviceId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Select service</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
                @error('serviceId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Label *" />
                <x-text-input wire:model="label" type="text" class="mt-1 block w-full" placeholder="e.g. Website URL" />
                @error('label') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="URL *" />
                <x-text-input wire:model="url" type="url" class="mt-1 block w-full" placeholder="https://…" />
                @error('url') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center gap-3 md:col-span-3">
                <x-primary-button wire:click="save" type="button">Save link</x-primary-button>
                <button wire:click="cancel" type="button" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="mt-3">
        @forelse ($groupedLinks as $serviceName => $group)
            <div class="mt-3 first:mt-0">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $serviceName }}</h4>
                <div class="divide-y divide-gray-100">
                    @foreach ($group as $link)
                        <div class="flex items-center justify-between py-2">
                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                               class="text-sm font-medium text-indigo-600 hover:underline">
                                {{ $link->label }}
                            </a>
                            @if ($canManage)
                                <div class="flex items-center gap-3 text-xs">
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
            <p class="py-3 text-sm text-gray-400">No service links added yet.</p>
        @endforelse
    </div>
</div>
