<x-app-layout>
    <x-slot name="header">Help</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Recommended for you</h2>
            <p class="mt-1 text-sm text-gray-500">Guides for your role. Start with Getting Started.</p>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($recommended as $slug)
                    <a href="{{ route('help.show', $slug) }}"
                       class="flex items-center justify-between rounded-md border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                        <span>{{ $guides[$slug] }}</span>
                        <span aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">All guides</h2>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @foreach ($guides as $slug => $title)
                    <li>
                        <a href="{{ route('help.show', $slug) }}" class="flex items-center justify-between py-2.5 text-gray-700 hover:text-indigo-600">
                            <span>{{ $title }}</span>
                            <span class="text-gray-300" aria-hidden="true">→</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-gray-400">Printable PDF versions of these guides are in the project's <code>docs/user-guides/pdf/</code> folder.</p>
        </div>
    </div>
</x-app-layout>
