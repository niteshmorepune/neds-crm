@props(['announcements'])

@if ($announcements->isNotEmpty())
    <div class="space-y-3">
        @foreach ($announcements as $announcement)
            <div
                x-data="{ dismissed: localStorage.getItem('announcement-dismissed-{{ $announcement->id }}') === '1' }"
                x-show="!dismissed"
                style="display:none"
                @if ($announcement->is_pinned)
                    class="rounded-lg border border-amber-200 bg-amber-50 p-4"
                @else
                    class="rounded-lg border border-indigo-200 bg-indigo-50 p-4"
                @endif
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold {{ $announcement->is_pinned ? 'text-amber-900' : 'text-indigo-900' }}">
                            📣 {{ $announcement->title }}
                        </p>
                        <p class="mt-1 text-sm {{ $announcement->is_pinned ? 'text-amber-800' : 'text-indigo-800' }}">{{ $announcement->body }}</p>
                    </div>
                    <button
                        type="button"
                        @click="dismissed = true; localStorage.setItem('announcement-dismissed-{{ $announcement->id }}', '1')"
                        class="shrink-0 text-lg leading-none text-gray-400 hover:text-gray-600"
                        aria-label="Dismiss"
                    >&times;</button>
                </div>
            </div>
        @endforeach
    </div>
@endif
