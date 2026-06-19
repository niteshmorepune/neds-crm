<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('company.name') }} — Client Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gradient-to-br from-indigo-50 to-gray-100 min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="mb-8">
            <img src="{{ asset('images/neds-logo.png') }}" alt="{{ config('company.name') }}" style="height:56px;width:auto;display:block;margin:0 auto">
        </div>
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-md ring-1 ring-gray-100">
            {{ $slot }}
        </div>
        <p class="mt-6 text-xs text-gray-400">© {{ date('Y') }} {{ config('company.name') }} · Client Portal</p>
    </div>
</body>
</html>
