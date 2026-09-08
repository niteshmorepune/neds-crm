<div wire:poll.45s="poll">
    @if ($action)
        {{-- `inset-x-4` (not `w-full` with no left bound) keeps this pinned card off both
             screen edges on mobile — a bare `right-4` with `w-full` used to stretch it
             almost edge-to-edge, burying whatever sat at the bottom of the page underneath
             it. The minimize toggle is the actual fix for "this covers my Save button":
             a fixed, always-on-top banner will always risk sitting over *something* at the
             bottom of *some* page, so instead of guessing a safe spot, let the user shrink
             it to a small pill on demand — same "no true close" philosophy as Snooze, just
             visually out of the way rather than deferred. --}}
        <div x-data="{ minimized: false }"
             class="fixed inset-x-4 bottom-4 z-50 sm:inset-x-auto sm:right-4 sm:w-full sm:max-w-sm">
            <div x-show="minimized" x-cloak class="flex justify-end">
                <button type="button" @click="minimized = false"
                        class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-lg hover:bg-gray-50">
                    ✨ {{ $action['title'] }}
                    <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                    </svg>
                </button>
            </div>

            <div x-show="!minimized" x-cloak class="rounded-lg border border-indigo-200 bg-white p-4 shadow-lg">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-900">✨ {{ $action['title'] }}</p>
                    <button type="button" @click="minimized = true" class="shrink-0 text-gray-400 hover:text-gray-600" aria-label="Minimize">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 12h-15" />
                        </svg>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ $action['body'] }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    @if ($action['action_url'])
                        <a href="{{ $action['action_url'] }}"
                           @if ($action['external']) target="_blank" rel="noopener" @endif
                           class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                            {{ $action['action_label'] }}
                        </a>
                    @else
                        <button type="button" wire:click="complete" wire:loading.attr="disabled" wire:target="complete"
                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                            {{ $action['action_label'] }}
                        </button>
                    @endif
                    {{-- Custom (not <x-dropdown>) and deliberately opens UPWARD: this banner
                         is pinned to the bottom-right of the viewport, so a normally-downward
                         dropdown menu renders past the bottom edge of the screen with no way
                         to scroll it into view (this fixed-position banner never scrolls). --}}
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = ! open"
                                class="flex items-center text-xs text-gray-400 hover:text-gray-600">
                            Snooze
                            <svg class="ms-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open" x-transition style="display: none;" @click="open = false"
                             class="absolute bottom-full right-0 z-10 mb-2 w-44 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5">
                            @foreach (\App\Livewire\NextActionBanner::SNOOZE_TIERS as $tier => $label)
                                <button type="button" wire:click="snooze('{{ $tier }}')" wire:loading.attr="disabled" wire:target="snooze"
                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 disabled:opacity-50">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
