<div>
    @if ($canManage)
        <div class="mb-4">
            @if (! $showScheduler && ! $showPicker && ! $showManualForm)
                <div class="flex flex-wrap items-center gap-2">
                    @if ($featureEnabled && $connected)
                        <button type="button" wire:click="openScheduler"
                                class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                            + Create Meeting
                        </button>
                        <button type="button" wire:click="loadEvents" wire:loading.attr="disabled" wire:target="loadEvents"
                                class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <span wire:loading.remove wire:target="loadEvents">Import Meet Notes</span>
                            <span wire:loading wire:target="loadEvents">Loading Calendar…</span>
                        </button>
                    @endif
                    <button type="button" wire:click="openManualForm"
                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        + Log External Meeting
                    </button>
                </div>
            @endif

            @if ($featureEnabled && ! $connected)
                <p class="mt-2 text-sm text-gray-400">
                    <a href="{{ route('profile.edit') }}" class="text-indigo-600 hover:underline">Ask an admin to connect NEDS's Google account</a>
                    to create meetings or import Meet notes here — or log an external meeting (Zoom, Teams, etc.) using the button above.
                </p>
            @endif

            @if ($error)
                <p class="mt-2 text-xs text-red-600">{{ $error }}</p>
            @endif

            @if ($createdMeetLink)
                <div class="mt-2 rounded-md border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-sm text-emerald-800">Meeting created — the client's been emailed the invite. Share the link directly if needed:</p>
                    <p class="mt-1 break-all font-mono text-xs text-emerald-900">{{ $createdMeetLink }}</p>
                </div>
            @endif

            @if ($showScheduler)
                <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-medium text-gray-500">Create a Meet call — invites {{ $this->attendeeEmail() ?? 'no one (no email on file)' }}</p>
                        <button type="button" wire:click="cancelScheduler" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="datetime-local" wire:model="scheduleAt" class="rounded-md border-gray-300 text-sm shadow-sm" />
                        <button type="button" wire:click="createMeeting" wire:loading.attr="disabled" wire:target="createMeeting"
                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                            <span wire:loading.remove wire:target="createMeeting">Create & Send Invite</span>
                            <span wire:loading wire:target="createMeeting">Creating…</span>
                        </button>
                    </div>
                </div>
            @endif

            @if ($showPicker)
                <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-medium text-gray-500">Recent Meet calls (last 14 days)</p>
                        <button type="button" wire:click="cancelPicker" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($events as $event)
                            <li class="flex items-center justify-between py-2">
                                <div>
                                    <p class="text-sm text-gray-800">{{ $event['title'] }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $event['start']->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                                        @if (! empty($event['attendees']))
                                            · {{ implode(', ', array_slice($event['attendees'], 0, 3)) }}{{ count($event['attendees']) > 3 ? '…' : '' }}
                                        @endif
                                    </p>
                                </div>
                                <button type="button" wire:click="importEvent('{{ $event['id'] }}')" wire:loading.attr="disabled"
                                        class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                                    Import
                                </button>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-400">No Meet calls found in the last 14 days.</li>
                        @endforelse
                    </ul>
                </div>
            @endif

            @if ($showManualForm)
                <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-medium text-gray-500">Log a meeting held on another platform</p>
                        <button type="button" wire:click="cancelManualForm" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <x-input-label for="manualPlatform" value="Platform" />
                            <select id="manualPlatform" wire:model="manualPlatform" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach (\App\Enums\MeetingPlatform::cases() as $platform)
                                    @continue($platform === \App\Enums\MeetingPlatform::GoogleMeet)
                                    <option value="{{ $platform->value }}">{{ $platform->label() }}</option>
                                @endforeach
                            </select>
                            @error('manualPlatform') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <x-input-label for="manualOccurredAt" value="When" />
                            <input id="manualOccurredAt" type="datetime-local" wire:model="manualOccurredAt" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            @error('manualOccurredAt') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <x-input-label for="manualTitle" value="Title (optional)" />
                            <input id="manualTitle" type="text" wire:model="manualTitle" placeholder="e.g. Quarterly review call"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            @error('manualTitle') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <x-input-label for="manualDurationMinutes" value="Duration (mins, optional)" />
                            <input id="manualDurationMinutes" type="number" min="0" wire:model="manualDurationMinutes"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            @error('manualDurationMinutes') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="manualNotes" value="Notes / summary (optional)" />
                            <textarea id="manualNotes" wire:model="manualNotes" rows="3" placeholder="Paste notes or a transcript — we'll summarize it automatically if AI is enabled."
                                      class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                            @error('manualNotes') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button type="button" wire:click="saveManualMeeting" wire:loading.attr="disabled" wire:target="saveManualMeeting"
                                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveManualMeeting">Save meeting</span>
                                <span wire:loading wire:target="saveManualMeeting">Saving…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <ul class="space-y-3">
        @forelse ($meetings as $meeting)
            <li class="rounded-md border border-gray-100 p-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-800">
                        {{ $meeting->title }}
                        @unless ($meeting->isGoogleMeetImport())
                            <span class="ml-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $meeting->platform->label() }}</span>
                        @endunless
                    </p>
                    <p class="text-xs text-gray-400">
                        {{ $meeting->occurred_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                        @if ($meeting->duration_minutes) · {{ $meeting->duration_minutes }}m @endif
                        · {{ $meeting->user?->name }}
                    </p>
                </div>
                @if (! empty($meeting->attendees))
                    <p class="mt-1 text-xs text-gray-400">With: {{ implode(', ', $meeting->attendees) }}</p>
                @endif
                <div class="mt-2 flex items-center gap-3 text-xs">
                    @if ($meeting->drive_recording_url)
                        <a href="{{ $meeting->drive_recording_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">▶ Recording</a>
                    @endif
                    @if ($meeting->drive_transcript_url)
                        <a href="{{ $meeting->drive_transcript_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Transcript (Drive)</a>
                    @endif
                    @if ($canManage && $meeting->isGoogleMeetImport() && ! $meeting->drive_recording_url && ! $meeting->drive_transcript_url)
                        <button type="button" wire:click="syncRecording({{ $meeting->id }})" wire:loading.attr="disabled" wire:target="syncRecording({{ $meeting->id }})"
                                class="text-gray-500 hover:text-gray-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="syncRecording({{ $meeting->id }})">↻ Sync recording &amp; transcript</span>
                            <span wire:loading wire:target="syncRecording({{ $meeting->id }})">Checking…</span>
                        </button>
                    @endif
                </div>
                @if ($meeting->raw_transcript)
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-medium text-gray-500">{{ $meeting->isGoogleMeetImport() ? 'View transcript' : 'View notes' }}</summary>
                        <p class="mt-1 max-h-48 overflow-y-auto whitespace-pre-wrap text-xs text-gray-600">{{ $meeting->raw_transcript }}</p>
                    </details>
                @endif
                <livewire:meeting-summary :meeting-id="$meeting->id" :can-manage="$canManage" :key="'meeting-summary-'.$meeting->id" />
            </li>
        @empty
            <li class="text-sm text-gray-400">No meetings yet.</li>
        @endforelse
    </ul>
</div>
