{{-- Data-driven sidebar. $menuItems is injected by the AppServiceProvider
     view composer and reflects the logged-in user's role + per-user overrides. --}}

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

        <div class="h-16 flex items-center justify-between px-6 border-b border-gray-800">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-white">
                {{ config('app.name', 'NEDS CRM') }}
            </a>
            <button @click="sidebarOpen = false" class="text-gray-400 hover:text-white p-1" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @foreach ($menuItems as $item)
                @php($active = request()->routeIs($item->route))
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

{{-- ── Desktop sidebar (md and above) ── --}}
<aside class="hidden md:flex md:flex-col w-64 shrink-0 bg-gray-900 text-gray-300 min-h-screen">
    <div class="h-16 flex items-center px-6 border-b border-gray-800">
        <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-white">
            {{ config('app.name', 'NEDS CRM') }}
        </a>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        @foreach ($menuItems as $item)
            @php($active = request()->routeIs($item->route))
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
