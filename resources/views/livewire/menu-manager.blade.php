<div class="max-w-7xl mx-auto space-y-6">
    <x-slot name="header">Menu Controller</x-slot>

    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <strong>Note:</strong> the role grid controls actual route access. Per-user overrides only show or hide
        sidebar items — they never grant or remove a permission, which is always governed by roles and Policies.
    </div>

    {{-- Role defaults grid --}}
    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Menu item</th>
                    @foreach ($roles as $role)
                        <th class="px-4 py-3 text-center">{{ $role->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-900">{{ $item->label }}</span>
                            <span class="ml-1 text-xs text-gray-400">{{ $item->key }}</span>
                        </td>
                        @foreach ($roles as $role)
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox"
                                       wire:click="toggleRole({{ $item->id }}, '{{ $role->value }}')"
                                       @checked(in_array($role->value, $matrix[$item->id] ?? []))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Per-user overrides --}}
    <div class="rounded-lg bg-white p-6 shadow-sm">
        <h3 class="text-base font-semibold text-gray-900">Per-user sidebar overrides</h3>
        <p class="mt-1 text-sm text-gray-500">Show or hide individual items for one person (cosmetic only).</p>

        <div class="mt-4 max-w-sm">
            <x-input-label for="userSelect" value="User" />
            <select id="userSelect" wire:model.live="selectedUserId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">— Select a user —</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role->label() }})</option>
                @endforeach
            </select>
        </div>

        @if ($selectedUserId)
            <div class="mt-5 space-y-2">
                @foreach ($items as $item)
                    @php $current = $overrides[$item->id] ?? 'default'; @endphp
                    <div class="flex items-center justify-between gap-3 border-b border-gray-50 py-2">
                        <span class="text-sm text-gray-700">{{ $item->label }}</span>
                        <select wire:change="setOverride({{ $item->id }}, $event.target.value)"
                                class="rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="default" @selected($current === 'default')>Default</option>
                            <option value="granted" @selected($current === 'granted')>Show</option>
                            <option value="revoked" @selected($current === 'revoked')>Hide</option>
                        </select>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
