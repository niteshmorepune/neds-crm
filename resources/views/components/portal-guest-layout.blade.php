<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('company.name') }} — Client Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <div class="mb-6 text-xl font-semibold text-gray-800">{{ config('company.name') }}</div>
        <div class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm">
            {{ $slot }}
        </div>
        <p class="mt-6 text-xs text-gray-400">Client Portal</p>
    </div>
</body>
</html>
