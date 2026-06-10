@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('company.name') }} — Client Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 min-h-screen">
    @php($contact = auth('portal')->user())
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="{{ route('portal.home') }}" class="font-semibold text-gray-900">{{ config('company.name') }}</a>
                <nav class="hidden sm:flex items-center gap-4 text-sm">
                    <a href="{{ route('portal.home') }}" class="text-gray-600 hover:text-gray-900">Profile</a>
                    <a href="{{ route('portal.invoices.index') }}" class="text-gray-600 hover:text-gray-900">Invoices</a>
                    <a href="{{ route('portal.projects.index') }}" class="text-gray-600 hover:text-gray-900">Projects</a>
                    <a href="{{ route('portal.tickets.index') }}" class="text-gray-600 hover:text-gray-900">Tickets</a>
                </nav>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-gray-500">{{ $contact?->name }} · {{ $contact?->customer?->company_name }}</span>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button class="text-gray-600 hover:text-gray-900">Log out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        @if ($header)<h1 class="mb-4 text-xl font-semibold text-gray-900">{{ $header }}</h1>@endif
        {{ $slot }}
    </main>
</body>
</html>
