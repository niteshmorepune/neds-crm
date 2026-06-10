<x-portal-app-layout :header="$project->name">
    <div class="rounded-lg bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
            <div><span class="text-gray-400">Status:</span> {{ $project->status->label() }}</div>
            <div><span class="text-gray-400">Timeline:</span> {{ $project->start_date?->format('d M Y') ?? '—' }} → {{ $project->end_date?->format('d M Y') ?? '—' }}</div>
        </dl>
        @if ($project->description)
            <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $project->description }}</p>
        @endif
        <p class="mt-4 text-xs text-gray-400">For detailed updates, please contact your account manager.</p>
    </div>
    <div class="mt-4"><a href="{{ route('portal.projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to projects</a></div>
</x-portal-app-layout>
