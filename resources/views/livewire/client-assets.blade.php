<div>
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Assets &amp; Documents</h2>
            <p class="mt-1 text-xs text-gray-500">Brand assets, website content, social assets, and business documents for this client — "Replace" keeps every prior version downloadable.</p>
        </div>
        @if ($canManage && ! $showForm)
            <button wire:click="newAsset" type="button"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                Upload asset
            </button>
        @endif
    </div>

    @if ($showForm)
        <div class="mt-4 grid grid-cols-1 gap-3 rounded-md border border-gray-200 p-4 md:grid-cols-2">
            <div>
                <x-input-label value="Title *" />
                <x-text-input wire:model="title" type="text" class="mt-1 block w-full" placeholder="e.g. Logo pack" />
                @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Category *" />
                <select wire:model="category" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Select category</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                    @endforeach
                </select>
                @error('category') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Service (optional)" />
                <select wire:model="serviceId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">— Client-wide —</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="File *" />
                <input type="file" wire:model="file" class="mt-1 block w-full text-sm" />
                @error('file') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center gap-3 md:col-span-2">
                <x-primary-button wire:click="save" type="button">Upload</x-primary-button>
                <button wire:click="cancel" type="button" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <select wire:model.live="filterCategory" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All categories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-2">
        @forelse ($groupedAssets as $categoryKey => $group)
            <div class="mt-4 first:mt-0">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    {{ \App\Enums\ClientAssetCategory::from($categoryKey)->label() }}
                </h3>
                <div class="divide-y divide-gray-100">
                    @foreach ($group as $asset)
                        <div class="py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('client-assets.download', $asset) }}" class="font-medium text-indigo-600 hover:underline">{{ $asset->title }}</a>
                                    <p class="text-xs text-gray-400">
                                        {{ $asset->original_name }} · {{ $asset->humanSize() }} · v{{ $asset->version }}
                                        @if ($asset->service) · {{ $asset->service->name }} @endif
                                        · {{ $asset->uploader?->name ?? 'System' }}
                                    </p>
                                </div>
                                @if ($canManage)
                                    <div class="flex shrink-0 items-center gap-3 text-xs">
                                        @if ($asset->versions->isNotEmpty())
                                            <button wire:click="toggleVersions({{ $asset->id }})" class="text-gray-500 hover:text-gray-700">
                                                {{ in_array($asset->id, $expanded) ? 'Hide' : $asset->versions->count().' older ' }}{{ in_array($asset->id, $expanded) ? '' : ($asset->versions->count() === 1 ? 'version' : 'versions') }}
                                            </button>
                                        @endif
                                        <button wire:click="startReplace({{ $asset->id }})" class="text-indigo-600 hover:underline">Replace</button>
                                        <button wire:click="delete({{ $asset->id }})" wire:confirm="Delete this asset and all its versions?" class="text-red-600 hover:text-red-500">Delete</button>
                                    </div>
                                @endif
                            </div>

                            @if (in_array($asset->id, $expanded) && $asset->versions->isNotEmpty())
                                <ul class="mt-2 space-y-1 border-l-2 border-gray-100 pl-3">
                                    @foreach ($asset->versions as $version)
                                        <li class="text-xs text-gray-500">
                                            <a href="{{ route('client-asset-versions.download', $version) }}" class="text-indigo-600 hover:underline">v{{ $version->version }} · {{ $version->original_name }}</a>
                                            ({{ $version->humanSize() }}) — {{ $version->uploader?->name ?? 'System' }}, {{ $version->created_at->format('d M Y') }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($canManage && $replacingId === $asset->id)
                                <div class="mt-2 flex flex-wrap items-center gap-2 rounded-md border border-gray-200 p-3">
                                    <input type="file" wire:model="replacementFile" class="text-xs" />
                                    <x-primary-button wire:click="replace" type="button">Upload new version</x-primary-button>
                                    <button wire:click="cancelReplace" type="button" class="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                                    @error('replacementFile') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="py-3 text-sm text-gray-400">No assets uploaded yet.</p>
        @endforelse
    </div>
</div>
