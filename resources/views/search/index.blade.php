<x-app-layout>
    <x-slot name="header">Search</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        <form method="GET" action="{{ route('search') }}" class="flex items-center gap-2">
            <input type="search" name="q" value="{{ $term }}" autofocus placeholder="Search clients, leads, deals, invoices, tickets, projects…"
                   class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Search</button>
        </form>

        @if ($term !== '')
            @forelse ($sections as $section)
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $section['type'] }}</h3>
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($section['results'] as $r)
                            <li class="py-2">
                                <a href="{{ $r['url'] }}" class="flex items-center justify-between hover:text-indigo-600">
                                    <span class="text-sm font-medium text-gray-900">{{ $r['label'] }}</span>
                                    <span class="text-xs text-gray-400">{{ $r['sub'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="rounded-lg bg-white p-6 text-center text-sm text-gray-400 shadow-sm">No results for “{{ $term }}”.</p>
            @endforelse
        @endif
    </div>
</x-app-layout>
