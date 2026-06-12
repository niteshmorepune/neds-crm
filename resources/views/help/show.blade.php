<x-app-layout>
    <x-slot name="header">Help — {{ $title }}</x-slot>

    <div class="max-w-6xl mx-auto grid grid-cols-1 gap-6 lg:grid-cols-4">
        {{-- Guide nav --}}
        <aside class="lg:col-span-1">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <a href="{{ route('help') }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">← All help</a>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($guides as $slug => $label)
                        <li>
                            <a href="{{ route('help.show', $slug) }}"
                               @class([
                                   'block rounded-md px-2 py-1.5',
                                   'bg-indigo-50 font-medium text-indigo-700' => $slug === $current,
                                   'text-gray-600 hover:bg-gray-50' => $slug !== $current,
                               ])>{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Rendered guide --}}
        <div class="lg:col-span-3">
            <article class="help-content rounded-lg bg-white p-6 shadow-sm sm:p-8">{!! $html !!}</article>
        </div>
    </div>

    @push('scripts')
        <style>
            .help-content { color: #374151; font-size: 14px; line-height: 1.65; }
            .help-content h1 { font-size: 1.6rem; font-weight: 700; color: #111827; border-bottom: 2px solid #e0e7ff; padding-bottom: .4rem; margin: 0 0 1rem; }
            .help-content h2 { font-size: 1.2rem; font-weight: 600; color: #4f46e5; margin: 1.6rem 0 .6rem; }
            .help-content h3 { font-size: 1rem; font-weight: 600; color: #111827; margin: 1.2rem 0 .4rem; }
            .help-content p { margin: .6rem 0; }
            .help-content ul { list-style: disc; margin: .6rem 0 .6rem 1.25rem; }
            .help-content ol { list-style: decimal; margin: .6rem 0 .6rem 1.5rem; }
            .help-content li { margin: .25rem 0; }
            .help-content a { color: #4f46e5; text-decoration: underline; }
            .help-content code { background: #eef2ff; color: #4338ca; padding: 1px 6px; border-radius: 4px; font-size: .85em; }
            .help-content pre { background: #1f2937; color: #f9fafb; padding: .9rem 1rem; border-radius: 8px; overflow-x: auto; margin: .8rem 0; }
            .help-content pre code { background: transparent; color: inherit; padding: 0; }
            .help-content strong { color: #111827; }
            .help-content hr { border: none; border-top: 1px solid #e5e7eb; margin: 1.4rem 0; }
            .help-content blockquote { margin: 1rem 0; padding: .6rem 1rem; background: #fffbeb; border-left: 3px solid #f59e0b; color: #92400e; border-radius: 0 6px 6px 0; }
            .help-content table { border-collapse: collapse; width: 100%; margin: 1rem 0; font-size: .9em; }
            .help-content th { background: #111827; color: #fff; text-align: left; padding: .5rem .7rem; }
            .help-content td { border: 1px solid #e5e7eb; padding: .5rem .7rem; vertical-align: top; }
            .help-content tbody tr:nth-child(even) { background: #f9fafb; }
        </style>
    @endpush
</x-app-layout>
