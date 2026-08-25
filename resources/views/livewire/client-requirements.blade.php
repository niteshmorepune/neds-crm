<div class="rounded-lg bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Client Requirements</h2>
            <p class="mt-1 text-xs text-gray-500">What this client needs to provide per service — dates, who's chasing it, and the file once received.</p>
        </div>
        @if ($canManage && ! $showForm)
            <button wire:click="newRequirement" type="button"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                Add requirement
            </button>
        @endif
    </div>

    @if ($showForm)
        <div class="mt-4 grid grid-cols-1 gap-3 rounded-md border border-gray-200 p-4 md:grid-cols-2">
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
                <x-input-label value="Title *" />
                <x-text-input wire:model="title" type="text" class="mt-1 block w-full" placeholder="e.g. Company logo files" />
                @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <x-input-label value="Instructions" />
                <x-text-input wire:model="instructions" type="text" class="mt-1 block w-full" placeholder="Optional" />
                @error('instructions') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Requested date" />
                <x-text-input wire:model="requestedDate" type="date" class="mt-1 block w-full" />
                @error('requestedDate') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Due date" />
                <x-text-input wire:model="dueDate" type="date" class="mt-1 block w-full" />
                @error('dueDate') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Responsible" />
                <select wire:model="responsibleUserId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">—</option>
                    @foreach ($staff as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-3 md:col-span-2">
                <x-primary-button wire:click="save" type="button">Save requirement</x-primary-button>
                <button wire:click="cancel" type="button" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    @if (count($groupedRequirements) > 1 || $filterServiceId)
        <div class="mt-4">
            <select wire:model.live="filterServiceId" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All services</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="mt-4">
        @forelse ($groupedRequirements as $serviceName => $group)
            <div class="mt-4 first:mt-0">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $serviceName }}</h3>
                <ul class="mt-1 divide-y divide-gray-100 text-sm">
                    @foreach ($group as $item)
                        <li class="py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="font-medium text-gray-900">{{ $item->title }}</span>
                                    <span class="ml-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span>
                                    @if ($item->instructions)
                                        <p class="mt-0.5 text-xs text-gray-500 whitespace-pre-line">{{ $item->instructions }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-gray-400">
                                        @if ($item->requested_date) Requested {{ $item->requested_date->format('d M Y') }} @endif
                                        @if ($item->due_date) · Due {{ $item->due_date->format('d M Y') }} @endif
                                        @if ($item->received_date) · Received {{ $item->received_date->format('d M Y') }} @endif
                                        @if ($item->responsible) · {{ $item->responsible->name }} @endif
                                    </p>
                                    @if ($item->clientAsset)
                                        <a href="{{ route('client-assets.download', $item->clientAsset) }}" class="mt-1 inline-block text-xs text-indigo-600 hover:underline">
                                            {{ $item->clientAsset->original_name }} ({{ $item->clientAsset->humanSize() }})
                                        </a>
                                    @endif
                                </div>
                                @if ($canManage)
                                    <div class="flex shrink-0 items-center gap-2">
                                        <select wire:change="updateStatus({{ $item->id }}, $event.target.value)" class="rounded-md border-gray-300 text-xs shadow-sm">
                                            @foreach (\App\Enums\DeliverableStatus::cases() as $status)
                                                <option value="{{ $status->value }}" @selected($item->status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                        <button wire:click="startUpload({{ $item->id }})" class="text-xs text-indigo-600 hover:underline">
                                            {{ $item->clientAsset ? 'Replace file' : 'Attach file' }}
                                        </button>
                                        <button wire:click="remove({{ $item->id }})" wire:confirm="Remove this requirement?" class="text-xs text-red-600 hover:text-red-500">Remove</button>
                                    </div>
                                @endif
                            </div>

                            @if ($canManage && $uploadingId === $item->id)
                                <div class="mt-2 flex flex-wrap items-center gap-2 rounded-md border border-gray-200 p-3">
                                    <input type="file" wire:model="file" class="text-xs" />
                                    <select wire:model="fileCategory" class="rounded-md border-gray-300 text-xs shadow-sm">
                                        <option value="">Category: Other</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->value }}">{{ $category->label() }}</option>
                                        @endforeach
                                    </select>
                                    <x-primary-button wire:click="uploadFile" type="button">Upload</x-primary-button>
                                    <button wire:click="cancelUpload" type="button" class="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                                    @error('file') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="py-3 text-sm text-gray-400">No requirements tracked yet.</p>
        @endforelse
    </div>
</div>
