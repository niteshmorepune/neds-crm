{{-- Data-driven sidebar. $menuItems is injected by the AppServiceProvider
     view composer and reflects the logged-in user's role + per-user overrides.
     Grouped into collapsible sections (App\Enums\MenuGroup) purely for
     display — access is still governed entirely by menu.access:{key}
     middleware / menu_item_role, unaffected by grouping.

     Accordion behavior (2026-08-26, owner-reported): exactly one group is
     open at a time — whichever contains the current page — every other
     group starts collapsed. This is deliberately NOT persisted across page
     loads (no localStorage) — every navigation is a real full-page request
     in this app (no SPA), so "collapse everything except where I am now" is
     recomputed fresh each time rather than carried over from a previous,
     possibly different page's manual toggling. A user can still expand
     another group to browse within the current page load; it just won't
     stick after the next click takes them somewhere else. --}}

@php
    $groupedMenuItems = $menuItems->groupBy('group');
@endphp

{{-- ── Mobile overlay (visible < md, controlled by sidebarOpen in parent x-data) ── --}}
<div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 flex md:hidden">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-gray-900/75" @click="sidebarOpen = false"></div>

    {{-- Panel --}}
    <div class="relative flex flex-col w-64 max-w-xs bg-gray-900 text-gray-300 h-full"
         x-transition:enter="transition ease-in-out duration-200 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full">

        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800 bg-white">
            <a href="{{ route('dashboard') }}">
                <img src="{{ asset('images/neds-logo.png') }}" alt="Niranjan Enterprises Digital Solutions" style="height:40px;width:auto">
            </a>
            <button @click="sidebarOpen = false" class="text-gray-400 hover:text-white p-1" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-3 overflow-y-auto">
            @foreach ($groupedMenuItems as $groupKey => $itemsInGroup)
                @php
                    $groupIsActive = $itemsInGroup->contains(fn ($item) => request()->routeIs(...$item->activePatterns()));
                @endphp
                <div x-data="{ open: {{ $groupIsActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open"
                            class="flex w-full items-center justify-between px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-300">
                        <span>{{ $itemsInGroup->first()->group?->label() ?? 'Other' }}</span>
                        <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="{ '-rotate-90': !open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" x-transition class="space-y-1">
                        @foreach ($itemsInGroup as $item)
                            @php
                                $active = request()->routeIs(...$item->activePatterns());
                            @endphp
                            <a href="{{ route($item->route) }}" @click="sidebarOpen = false"
                               @class([
                                   'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors',
                                   'bg-gray-800 text-white' => $active,
                                   'text-gray-300 hover:bg-gray-800 hover:text-white' => ! $active,
                               ])>
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3" y="3" width="7" height="7" rx="1.5" />
                                    <rect x="14" y="3" width="7" height="7" rx="1.5" />
                                    <rect x="3" y="14" width="7" height="7" rx="1.5" />
                                    <rect x="14" y="14" width="7" height="7" rx="1.5" />
                                </svg>
                                <span>{{ $item->label }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- Quick links only visible in mobile menu --}}
        <div class="sm:hidden px-3 py-2 border-t border-gray-800 space-y-1">
            <a href="{{ route('help') }}" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white">
                <span>? Help</span>
            </a>
            @if (Auth::user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager, \App\Enums\UserRole::Sales, \App\Enums\UserRole::Support))
                <a href="{{ route('calls.create') }}" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white">
                    <span>☎ Log a call</span>
                </a>
            @endif
        </div>

        <div class="px-3 py-4 border-t border-gray-800">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M18 15l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    <span>{{ __('Logout') }}</span>
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ── Desktop sidebar (md and above) ──
     Deliberately sticky + capped to viewport height so it scrolls
     independently of the main content column. Before this fix, <aside> only
     had min-h-screen, so on a tall page (a long table, for instance) it grew
     to match its sibling instead of clipping — there was no real internal
     scroll container, so anything that scrolled an item into view actually
     scrolled the whole window, landing the page wherever the sidebar item
     happened to be rather than at the top. With the accordion above, this
     matters less day-to-day (at most ~10 items are ever visible at once),
     but it's the actual root-cause fix, not just a smaller sidebar. --}}
<aside class="hidden md:sticky md:top-0 md:flex md:h-screen md:flex-col w-64 shrink-0 bg-gray-900 text-gray-300">
    <div class="flex items-center px-4 py-3 border-b border-gray-800 bg-white">
        <a href="{{ route('dashboard') }}">
            <img src="{{ asset('images/neds-logo.png') }}" alt="Niranjan Enterprises Digital Solutions" style="height:40px;width:auto">
        </a>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-3 overflow-y-auto">
        @foreach ($groupedMenuItems as $groupKey => $itemsInGroup)
            @php
                $groupIsActive = $itemsInGroup->contains(fn ($item) => request()->routeIs(...$item->activePatterns()));
            @endphp
            <div x-data="{ open: {{ $groupIsActive ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open"
                        class="flex w-full items-center justify-between px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-300">
                    <span>{{ $itemsInGroup->first()->group?->label() ?? 'Other' }}</span>
                    <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="{ '-rotate-90': !open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" x-transition class="space-y-1">
                    @foreach ($itemsInGroup as $item)
                        @php
                            $active = request()->routeIs(...$item->activePatterns());
                        @endphp
                        <a href="{{ route($item->route) }}"
                           @class([
                               'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors',
                               'bg-gray-800 text-white' => $active,
                               'text-gray-300 hover:bg-gray-800 hover:text-white' => ! $active,
                           ])>
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="3" width="7" height="7" rx="1.5" />
                                <rect x="14" y="3" width="7" height="7" rx="1.5" />
                                <rect x="3" y="14" width="7" height="7" rx="1.5" />
                                <rect x="14" y="14" width="7" height="7" rx="1.5" />
                            </svg>
                            <span>{{ $item->label }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="px-3 py-4 border-t border-gray-800">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M18 15l3-3m0 0l-3-3m3 3H9" />
                </svg>
                <span>{{ __('Logout') }}</span>
            </button>
        </form>
    </div>
</aside>
