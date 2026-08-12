@props(['header' => null, 'title' => null])

@php
    $partner = auth('partner')->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? $header) ? ($title ?? $header) . ' — ' : '' }}{{ config('company.name') }} Partner Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen">

    <header class="sticky top-0 z-20 bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto flex items-center justify-between h-16 px-4 lg:px-8">
            <a href="{{ route('partner-portal.home') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/neds-logo.png') }}" alt="{{ config('company.name') }}" style="height:28px;width:auto">
                <span class="text-xs font-semibold text-indigo-600 tracking-wide uppercase">Partner Portal</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="{{ route('partner-portal.faq') }}"
                   class="text-sm font-medium {{ request()->routeIs('partner-portal.faq') ? 'text-indigo-700' : 'text-gray-600 hover:text-gray-900' }}">FAQ</a>
                <span class="hidden sm:inline text-sm font-medium text-gray-700">{{ $partner?->name }}</span>
                <form method="POST" action="{{ route('partner-portal.logout') }}">
                    @csrf
                    <button class="text-xs text-gray-500 hover:text-red-600 transition-colors">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 lg:px-8 lg:py-8">
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
            © {{ date('Y') }} {{ config('company.name') }} · Partner Portal
        </div>
    </footer>

</body>
</html>
