<x-app-layout>
    <x-slot name="header">{{ $contentPiece->title }}</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('upload_link'))
            <div class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm" x-data="{ copied: false }">
                <p class="font-medium text-indigo-800 mb-1">Partner upload link (valid 7 days)</p>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ session('upload_link') }}"
                           class="flex-1 rounded border border-indigo-200 bg-white px-2 py-1 text-xs text-gray-700 font-mono"
                           onclick="this.select()" />
                    <button type="button" onclick="navigator.clipboard.writeText('{{ session('upload_link') }}'); this.textContent='Copied!'"
                            class="shrink-0 rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-500">
                        Copy
                    </button>
                </div>
                <p class="mt-1 text-xs text-indigo-600">Share this link with the partner — no login needed. Expires in 7 days.</p>
            </div>
        @endif

        {{-- Main detail card --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold text-gray-900">{{ $contentPiece->title }}</h1>
                        @if ($contentPiece->festival)
                            <span class="inline-flex rounded px-2 py-0.5 text-sm font-medium bg-pink-100 text-pink-700">🎉 {{ $contentPiece->festival->name }}</span>
                        @endif
                        <span class="inline-flex rounded px-2 py-0.5 text-sm font-medium {{ $contentPiece->status->badgeClass() }}">
                            {{ $contentPiece->status->label() }}
                        </span>
                    </div>
                    <dl class="grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                        <div><span class="text-gray-400">Project:</span>
                            <a href="{{ route('projects.show', $contentPiece->project) }}" class="text-indigo-600 hover:underline">{{ $contentPiece->project->name }}</a>
                        </div>
                        <div><span class="text-gray-400">Workflow:</span> {{ $contentPiece->workflow_type->label() }}</div>
                        <div><span class="text-gray-400">Platform:</span> {{ $contentPiece->platform->label() }}</div>
                        <div><span class="text-gray-400">Partner:</span> {{ $contentPiece->partner?->name ?? '—' }}</div>
                        <div><span class="text-gray-400">Publish date:</span> {{ $contentPiece->publish_date?->format('d M Y') ?? '—' }}</div>
                        @if ($contentPiece->published_at)
                            <div><span class="text-gray-400">Published:</span> {{ $contentPiece->published_at->setTimezone('Asia/Kolkata')->format('d M Y, H:i') }}</div>
                        @endif
                        <div><span class="text-gray-400">Created by:</span> {{ $contentPiece->creator?->name ?? '—' }}</div>
                    </dl>

                    @if ($contentPiece->google_drive_link)
                        <a href="{{ $contentPiece->google_drive_link }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:underline">
                            View on Google Drive ↗
                        </a>
                    @endif
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    @can('update', $contentPiece)
                        <a href="{{ route('content.edit', $contentPiece) }}"
                           class="rounded-md bg-white border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                    @endcan
                    @can('delete', $contentPiece)
                        <form method="POST" action="{{ route('content.destroy', $contentPiece) }}"
                              onsubmit="return confirm('Delete this content piece?')">
                            @csrf @method('DELETE')
                            <button class="text-sm font-medium text-red-600 hover:text-red-500">Delete</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Advance status --}}
        @if (!empty($allowedNext))
            @can('update', $contentPiece)
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900 mb-3">Advance Status</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($allowedNext as $next)
                            @php $nextStatus = App\Enums\ContentStatus::from($next); @endphp
                            <form method="POST" action="{{ route('projects.content.advance', ['project' => $contentPiece->project_id, 'content_piece' => $contentPiece]) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $next }}" />
                                <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Mark as {{ $nextStatus->label() }} →
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endcan
        @endif

        {{-- Copy / Brief --}}
        @if ($contentPiece->copy_text)
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Copy / Brief</h2>
                <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">{{ $contentPiece->copy_text }}</div>
            </div>
        @endif

        {{-- Upload link generator --}}
        @can('generateUploadLink', $contentPiece)
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900 mb-1">Partner Upload Link</h2>
                <p class="text-sm text-gray-500 mb-3">Generate a secure link to share with the partner so they can upload files directly — no CRM login needed. Link is valid for 7 days.</p>
                @if ($contentPiece->isUploadTokenValid())
                    <p class="text-xs text-gray-500 mb-2">Current link expires: {{ $contentPiece->upload_token_expires_at->setTimezone('Asia/Kolkata')->format('d M Y, H:i') }}</p>
                @endif
                <form method="POST" action="{{ route('projects.content.upload-link', ['project' => $contentPiece->project_id, 'content_piece' => $contentPiece]) }}">
                    @csrf
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        {{ $contentPiece->isUploadTokenValid() ? 'Refresh Upload Link' : 'Generate Upload Link' }}
                    </button>
                </form>
            </div>
        @endcan

        {{-- Internal notes --}}
        @if ($contentPiece->notes)
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900 mb-2">Internal Notes</h2>
                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $contentPiece->notes }}</p>
            </div>
        @endif

        {{-- Attachments --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900 mb-3">
                Attachments
                @if ($contentPiece->attachments->isNotEmpty())
                    <span class="text-gray-400 font-normal text-sm">({{ $contentPiece->attachments->count() }})</span>
                @endif
            </h2>
            @if ($contentPiece->attachments->isEmpty())
                <p class="text-sm text-gray-400">No files uploaded yet.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($contentPiece->attachments as $attachment)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <div>
                                <a href="{{ route('attachments.download', $attachment) }}"
                                   class="font-medium text-indigo-600 hover:underline">{{ $attachment->original_name }}</a>
                                <span class="ml-2 text-gray-400">{{ $attachment->humanSize() }}</span>
                                @if ($attachment->uploaded_by === null)
                                    <span class="ml-2 inline-flex rounded bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-700">Partner upload</span>
                                @endif
                            </div>
                            @can('delete', $attachment->attachable)
                                <form method="POST" action="{{ route('attachments.destroy', $attachment) }}"
                                      onsubmit="return confirm('Delete this file?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
