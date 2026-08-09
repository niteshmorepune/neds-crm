<div>
    @foreach ($participants as $participant)
        @php
            $meeting = $participant->meeting;
            $clientName = $meeting->meetable instanceof \App\Models\Customer
                ? $meeting->meetable?->company_name
                : $meeting->meetable?->name;
            $when = $meeting->occurred_at->timezone(config('app.display_timezone'));
        @endphp
        <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-indigo-50 text-sm">📅</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900">
                    {{ $meeting->title }}
                    @if ($clientName)
                        <span class="text-gray-400">— {{ $clientName }}</span>
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-gray-500">
                    {{ $when->isToday() ? 'Today' : $when->format('d M Y') }}, {{ $when->format('g:i A') }}
                    · Organised by {{ $meeting->user?->name ?? 'NEDS' }}
                    @if ($meeting->meet_link)
                        · <a href="{{ $meeting->meet_link }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">Join Google Meet</a>
                    @endif
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($participant->status->value === 'pending')
                    <button type="button" wire:click="respond({{ $participant->id }}, 'declined')" class="text-xs text-gray-500 hover:text-gray-700">Decline</button>
                    <button type="button" wire:click="respond({{ $participant->id }}, 'accepted')" class="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-emerald-500">Accept</button>
                @else
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-50 text-emerald-700' => $participant->status->value === 'accepted',
                        'bg-gray-100 text-gray-600' => $participant->status->value === 'declined',
                    ])>{{ $participant->status->label() }}</span>
                @endif
            </div>
        </div>
    @endforeach
</div>
