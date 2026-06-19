@props(['header' => null])

@php
    $navLinks = [
        ['label' => 'Dashboard', 'route' => 'portal.home',           'pattern' => 'portal.home'],
        ['label' => 'Invoices',  'route' => 'portal.invoices.index', 'pattern' => 'portal.invoices.*'],
        ['label' => 'Projects',  'route' => 'portal.projects.index', 'pattern' => 'portal.projects.*'],
        ['label' => 'Tickets',   'route' => 'portal.tickets.index',  'pattern' => 'portal.tickets.*'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $header ? $header . ' — ' : '' }}{{ config('company.name') }} Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen">
    @php($contact = auth('portal')->user())

    <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ route('portal.home') }}">
                    <img src="{{ asset('images/neds-logo.png') }}" alt="{{ config('company.name') }}" style="height:36px;width:auto;display:block">
                </a>
                <nav class="hidden sm:flex items-center gap-1 text-sm">
                    @foreach ($navLinks as $link)
                        <a href="{{ route($link['route']) }}"
                           class="px-3 py-1.5 rounded-md font-medium transition-colors
                                  {{ request()->routeIs($link['pattern'])
                                      ? 'bg-indigo-50 text-indigo-700'
                                      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <div class="hidden sm:block text-right">
                    <div class="text-sm font-medium text-gray-800">{{ $contact?->name }}</div>
                    <div class="text-xs text-gray-400">{{ $contact?->customer?->company_name }}</div>
                </div>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button class="text-sm text-gray-500 hover:text-red-600 transition-colors">Log out</button>
                </form>
            </div>
        </div>

        {{-- Mobile nav --}}
        <div class="sm:hidden border-t border-gray-100 overflow-x-auto">
            <div class="flex px-4 py-2 gap-1">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="shrink-0 px-3 py-1.5 rounded-md text-sm font-medium transition-colors
                              {{ request()->routeIs($link['pattern'])
                                  ? 'bg-indigo-50 text-indigo-700'
                                  : 'text-gray-600 hover:text-gray-900' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($header)
            <h1 class="mb-6 text-2xl font-semibold text-gray-900">{{ $header }}</h1>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-gray-200 mt-12">
        <div class="max-w-5xl mx-auto px-4 py-4 text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ config('company.name') }} · Client Portal
        </div>
    </footer>
</body>
</html>
