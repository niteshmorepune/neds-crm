<x-app-layout>
    <x-slot name="header">Content — {{ $project->name }}</x-slot>

    <div class="max-w-6xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ $project->name }}</a>

                {{-- Filters --}}
                <form method="GET" class="flex items-center gap-2">
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All statuses</option>
                        @foreach (App\Enums\ContentStatus::cases() as $s)
                            <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                    <select name="platform" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All platforms</option>
                        @foreach (App\Enums\ContentPlatform::cases() as $p)
                            <option value="{{ $p->value }}" @selected(request('platform') === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
                    @if (request('status') || request('platform'))
                        <a href="{{ route('projects.content.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
                    @endif
                </form>
            </div>

            @can('create', [App\Models\ContentPiece::class, $project])
                <a href="{{ route('projects.content.create', $project) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">+ Add piece</a>
            @endcan
        </div>

        @if ($project->google_drive_folder_link)
            <div class="flex items-center gap-2 rounded-md border border-blue-100 bg-blue-50 px-4 py-2 text-sm text-blue-700">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                <span>Shared Drive folder:</span>
                <a href="{{ $project->google_drive_folder_link }}" target="_blank" rel="noopener noreferrer" class="font-medium underline hover:text-blue-900">
                    Open in Google Drive ↗
                </a>
            </div>
        @endif

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Workflow</th>
                        <th class="px-4 py-3">Platform</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Partner</th>
                        <th class="px-4 py-3">Publish date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pieces as $piece)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('content.show', $piece) }}" class="font-medium text-indigo-600 hover:underline">{{ $piece->title }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $piece->workflow_type->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $piece->platform->label() }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $piece->status->badgeClass() }}">{{ $piece->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $piece->partner?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $piece->publish_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('content.show', $piece) }}" class="text-indigo-600 hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No content pieces yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
