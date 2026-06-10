<div class="rounded-lg bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">Contacts</h2>
        @if ($canManage && ! $showForm)
            <button wire:click="newContact"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                Add contact
            </button>
        @endif
    </div>

    @if ($showForm)
        <div class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-gray-200 p-4 md:grid-cols-2">
            <div>
                <x-input-label value="Name *" />
                <x-text-input wire:model="name" type="text" class="mt-1 block w-full" />
                @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Designation" />
                <x-text-input wire:model="designation" type="text" class="mt-1 block w-full" />
            </div>
            <div>
                <x-input-label value="Phone" />
                <x-text-input wire:model="phone" type="text" class="mt-1 block w-full" />
            </div>
            <div>
                <x-input-label value="Email" />
                <x-text-input wire:model="email" type="email" class="mt-1 block w-full" />
                @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
                <input type="checkbox" wire:model="is_primary" class="rounded border-gray-300 text-indigo-600" />
                Primary contact
            </label>
            <div class="flex items-center gap-3 md:col-span-2">
                <x-primary-button wire:click="save" type="button">Save contact</x-primary-button>
                <button wire:click="cancel" type="button" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="mt-4 divide-y divide-gray-100">
        @forelse ($contacts as $contact)
            <div class="flex items-center justify-between py-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900">{{ $contact->name }}</span>
                        @if ($contact->is_primary)
                            <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Primary</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ collect([$contact->designation, $contact->email, $contact->phone])->filter()->join(' · ') ?: '—' }}
                    </div>
                    @if ($contact->portal_enabled)
                        <span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                            {{ $contact->password_set_at ? 'Portal active' : 'Portal invited' }}
                        </span>
                    @endif
                </div>
                @if ($canManage)
                    <div class="flex items-center gap-3 text-sm">
                        @if ($contact->portal_enabled)
                            <button wire:click="revoke({{ $contact->id }})" wire:confirm="Revoke portal access?" class="text-amber-600 hover:text-amber-500">Revoke portal</button>
                        @elseif ($contact->email)
                            <button wire:click="invite({{ $contact->id }})" class="text-emerald-600 hover:text-emerald-500">Invite to portal</button>
                        @endif
                        @unless ($contact->is_primary)
                            <button wire:click="makePrimary({{ $contact->id }})" class="text-gray-500 hover:text-gray-700">Make primary</button>
                        @endunless
                        <button wire:click="edit({{ $contact->id }})" class="text-gray-500 hover:text-gray-700">Edit</button>
                        <button wire:click="delete({{ $contact->id }})"
                                wire:confirm="Delete this contact?"
                                class="text-red-600 hover:text-red-500">Delete</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-3 text-sm text-gray-400">No contacts yet.</p>
        @endforelse
    </div>
</div>
