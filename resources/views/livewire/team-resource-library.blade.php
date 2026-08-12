<div>
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
            Shared internal files (plugin builds, certificates, templates…). Leave "Visible to" empty to show a file
            to everyone.
        </p>
        @if ($canManage && ! $showForm)
            <button wire:click="newResource"
                    class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                Add file
            </button>
        @endif
    </div>

    @if ($showForm)
        <div class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-gray-200 p-4 md:grid-cols-2">
            <div>
                <x-input-label value="Title *" />
                <x-text-input wire:model="title" type="text" class="mt-1 block w-full" placeholder="e.g. WordPress SEO Plugin v3.2" />
                @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Category" />
                <select wire:model="category" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Uncategorized</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                    @endforeach
                </select>
                @error('category') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <x-input-label value="Description" />
                <textarea wire:model="description" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                @error('description') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            @unless ($editingId)
                <div class="md:col-span-2">
                    <x-input-label value="File *" />
                    <input type="file" wire:model="file" class="mt-1 block w-full text-sm text-gray-600" />
                    <div wire:loading wire:target="file" class="mt-1 text-xs text-gray-400">Uploading…</div>
                    @error('file') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            @endunless
            <div class="md:col-span-2">
                <x-input-label value="Visible to (leave blank for everyone)" />
                <div class="mt-1 flex flex-wrap gap-3">
                    @foreach ($assignableRoles as $role)
                        <label class="flex items-center gap-1.5 text-sm text-gray-700">
                            <input type="checkbox" wire:model="visibleRoles" value="{{ $role->value }}"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm" />
                            {{ $role->label() }}
                        </label>
                    @endforeach
                </div>
                @error('visibleRoles.*') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center gap-3 md:col-span-2">
                <x-primary-button wire:click="save" wire:loading.attr="disabled" type="button">Save file</x-primary-button>
                <button wire:click="cancel" type="button" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="mt-4">
        <select wire:model.live="filterCategory" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All categories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-2">
        @forelse ($groupedResources as $categoryKey => $group)
            <div class="mt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    {{ $categoryKey === '' ? 'Uncategorized' : \App\Enums\TeamResourceCategory::from($categoryKey)->label() }}
                </h3>
                <div class="divide-y divide-gray-100">
                    @foreach ($group as $resource)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <a href="{{ route('team-resources.download', $resource) }}"
                                   class="font-medium text-indigo-600 hover:underline">
                                    {{ $resource->title }}
                                </a>
                                <div class="text-xs text-gray-400">
                                    {{ $resource->humanSize() }} · uploaded by {{ $resource->uploader?->name ?? 'System' }}
                                    @if ($resource->description)
                                        · {{ $resource->description }}
                                    @endif
                                </div>
                            </div>
                            @if ($canManage)
                                <div class="flex items-center gap-3 text-sm">
                                    <button wire:click="edit({{ $resource->id }})" class="text-gray-500 hover:text-gray-700">Edit</button>
                                    <button wire:click="delete({{ $resource->id }})"
                                            wire:confirm="Delete this file?"
                                            class="text-red-600 hover:text-red-500">Delete</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="py-3 text-sm text-gray-400">No files added yet.</p>
        @endforelse
    </div>
</div>
