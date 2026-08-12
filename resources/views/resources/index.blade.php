<x-app-layout>
    <x-slot name="header">Resources</x-slot>

    <div class="max-w-3xl mx-auto space-y-4" x-data="{ tab: 'files' }">
        <p class="text-sm text-gray-500">
            Shared internal files and company-wide reference links for the team. Per-client links (website, GBP,
            socials, Drive…) live on each client's own page instead.
        </p>

        <div class="flex gap-1 border-b border-gray-200">
            <button type="button" @click="tab = 'files'"
                    :class="tab === 'files' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="border-b-2 px-3 py-2 text-sm font-medium">
                Files
            </button>
            <button type="button" @click="tab = 'links'"
                    :class="tab === 'links' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="border-b-2 px-3 py-2 text-sm font-medium">
                Links
            </button>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div x-show="tab === 'files'" x-cloak>
                <livewire:team-resource-library />
            </div>
            <div x-show="tab === 'links'" x-cloak>
                <livewire:important-links-manager />
            </div>
        </div>
    </div>
</x-app-layout>
