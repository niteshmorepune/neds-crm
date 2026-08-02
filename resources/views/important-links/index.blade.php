<x-app-layout>
    <x-slot name="header">Important Links</x-slot>

    <div class="max-w-3xl mx-auto space-y-4">
        <p class="text-sm text-gray-500">
            Company-wide official links (hosting, domain registrar, Google Workspace admin, etc.) for quick access by
            the team. Per-client links (website, GBP, socials, Drive…) live on each client's own page instead.
        </p>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <livewire:important-links-manager />
        </div>
    </div>
</x-app-layout>
