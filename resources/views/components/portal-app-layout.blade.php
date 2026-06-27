@props(['header' => null, 'title' => null])

@php
    $contact  = auth('portal')->user();
    $initials = collect(explode(' ', $contact?->name ?? 'U'))
        ->map(fn($p) => strtoupper(substr($p, 0, 1)))->take(2)->join('');

    $navLinks = [
        [
            'label'   => 'Dashboard',
            'route'   => 'portal.home',
            'pattern' => 'portal.home',
            'icon'    => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>',
        ],
        [
            'label'   => 'Services',
            'route'   => 'portal.services.index',
            'pattern' => 'portal.services.*',
            'icon'    => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
        ],
        [
            'label'   => 'Quotations',
            'route'   => 'portal.quotations.index',
            'pattern' => 'portal.quotations.*',
            'icon'    => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>',
        ],
        [
            'label'   => 'Invoices',
            'route'   => 'portal.invoices.index',
            'pattern' => 'portal.invoices.*',
            'icon'    => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
        ],
        [
            'label'   => 'Projects',
            'route'   => 'portal.projects.index',
            'pattern' => 'portal.projects.*',
            'icon'    => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>',
        ],
        [
            'label'   => 'Tickets',
            'route'   => 'portal.tickets.index',
            'pattern' => 'portal.tickets.*',
            'icon'    => '<svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? $header) ? ($title ?? $header) . ' — ' : '' }}{{ config('company.name') }} Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen" x-data="{ mobileMenuOpen: false }">

    {{-- ── MOBILE SIDEBAR OVERLAY ─────────────────────────────────────────── --}}
    <div x-show="mobileMenuOpen" class="fixed inset-0 z-40 lg:hidden" style="display:none">
        {{-- Backdrop --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/60"
             @click="mobileMenuOpen = false"></div>

        {{-- Slide-in panel --}}
        <div x-show="mobileMenuOpen"
             x-transition:enter="transition ease-in-out duration-200 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-200 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="relative flex flex-col w-64 h-full bg-white shadow-xl"
             @click.stop>

            {{-- Close button --}}
            <button @click="mobileMenuOpen = false"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            {{-- Brand --}}
            <div class="flex items-center gap-2 px-5 py-5 border-b border-gray-100">
                <img src="{{ asset('images/neds-logo.png') }}" alt="{{ config('company.name') }}" style="height:30px;width:auto">
                <span class="text-xs font-semibold text-indigo-600 tracking-wide uppercase">Portal</span>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
                @foreach ($navLinks as $link)
                    @php $active = request()->routeIs($link['pattern']); @endphp
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ $active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <span class="{{ $active ? 'text-indigo-600' : 'text-gray-400' }}">{!! $link['icon'] !!}</span>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Contact --}}
            <div class="px-4 py-4 border-t border-gray-100">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700 shrink-0">{{ $initials }}</div>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">{{ $contact?->name }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ $contact?->customer?->company_name }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button class="text-xs text-gray-500 hover:text-red-600 transition-colors">Sign out</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── DESKTOP SIDEBAR (always visible) ──────────────────────────────── --}}
    <div class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col z-30">
        <div class="flex flex-col h-full bg-white border-r border-gray-200">

            {{-- Brand --}}
            <div class="flex items-center gap-2 px-5 py-5 border-b border-gray-100">
                <img src="{{ asset('images/neds-logo.png') }}" alt="{{ config('company.name') }}" style="height:32px;width:auto">
                <span class="text-xs font-semibold text-indigo-600 tracking-wide uppercase">Portal</span>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
                @foreach ($navLinks as $link)
                    @php $active = request()->routeIs($link['pattern']); @endphp
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ $active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <span class="{{ $active ? 'text-indigo-600' : 'text-gray-400' }}">{!! $link['icon'] !!}</span>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Contact info + logout --}}
            <div class="px-4 py-4 border-t border-gray-100">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-700 shrink-0">{{ $initials }}</div>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">{{ $contact?->name }}</div>
                        <div class="text-xs text-gray-500 truncate">{{ $contact?->customer?->company_name }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button class="text-xs text-gray-500 hover:text-red-600 transition-colors">Sign out</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── CONTENT WRAPPER ────────────────────────────────────────────────── --}}
    <div class="lg:pl-64 flex flex-col min-h-screen">

        {{-- Mobile top bar --}}
        <header class="sticky top-0 z-20 bg-white border-b border-gray-200 lg:hidden">
            <div class="flex items-center justify-between h-14 px-4">
                <button @click="mobileMenuOpen = true" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <a href="{{ route('portal.home') }}">
                    <img src="{{ asset('images/neds-logo.png') }}" alt="{{ config('company.name') }}" style="height:28px;width:auto">
                </a>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button class="text-xs text-gray-500 hover:text-red-600 transition-colors">Sign out</button>
                </form>
            </div>
        </header>

        {{-- Main --}}
        <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
            <div class="max-w-5xl mx-auto">
                @if (session('status'))
                    <div class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($header)
                    <h1 class="mb-6 text-2xl font-bold text-gray-900">{{ $header }}</h1>
                @endif

                {{ $slot }}
            </div>
        </main>

        <footer class="border-t border-gray-200 mt-auto">
            <div class="max-w-5xl mx-auto px-4 lg:px-8 py-4 text-center text-xs text-gray-400">
                © {{ date('Y') }} {{ config('company.name') }} · Client Portal
            </div>
        </footer>
    </div>

</body>
</html>
