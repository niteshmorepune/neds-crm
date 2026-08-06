<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900">Client Deliverables</h2>
    <p class="mt-1 text-xs text-gray-500">Items the client needs to provide (logo, content, docs, access). Visible to them on the portal — they can upload files against each one.</p>

    <ul class="mt-4 divide-y divide-gray-100 text-sm">
        @forelse ($deliverables as $item)
            <li class="py-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="font-medium text-gray-900">{{ $item->title }}</span>
                        <span class="ml-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span>
                        @if ($item->instructions)
                            <p class="mt-0.5 text-xs text-gray-500 whitespace-pre-line">{{ $item->instructions }}</p>
                        @endif
                    </div>
                    @if ($canManage)
                        <div class="flex shrink-0 items-center gap-2">
                            <select wire:change="updateStatus({{ $item->id }}, $event.target.value)" class="rounded-md border-gray-300 text-xs shadow-sm">
                                @foreach (\App\Enums\DeliverableStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($item->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <button wire:click="removeDeliverable({{ $item->id }})" wire:confirm="Remove this deliverable?" class="text-red-600 hover:text-red-500 text-xs">Remove</button>
                        </div>
                    @endif
                </div>

                @if ($item->attachments->isNotEmpty())
                    <ul class="mt-2 space-y-1">
                        @foreach ($item->attachments as $attachment)
                            <li class="flex items-center gap-2 text-xs">
                                <a href="{{ route('attachments.download', $attachment) }}" class="text-indigo-600 hover:underline">{{ $attachment->original_name }}</a>
                                <span class="text-gray-400">{{ $attachment->uploaderName() }}{{ $attachment->contact_id ? ' (client)' : '' }} · {{ $attachment->humanSize() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @empty
            <li class="py-2 text-gray-400">No deliverables requested yet.</li>
        @endforelse
    </ul>

    @if ($canManage)
        <div class="mt-4 grid grid-cols-12 gap-2 border-t border-gray-100 pt-4">
            <div class="col-span-4">
                <input wire:model="title" placeholder="Title (e.g. Company logo)" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="col-span-7">
                <input wire:model="instructions" placeholder="Instructions for the client (optional)" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                @error('instructions') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <button wire:click="addDeliverable" type="button" class="col-span-1 rounded-md bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-500">Add</button>
        </div>
    @endif
</div>
