<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'NEDS CRM') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-6 pt-6 sm:pt-0">
            <div>
                <a href="/">
                    <img src="{{ asset('images/neds-logo-square.png') }}" alt="Niranjan Enterprises Digital Solutions" style="width:120px;height:auto;display:block;margin:0 auto">
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden bg-white px-6 py-8 text-center shadow-md sm:max-w-md sm:rounded-lg">
                <h1 class="text-lg font-semibold text-gray-900">Access restricted</h1>
                <p class="mt-2 text-sm text-gray-600">You do not have permission to perform this action. Please contact your administrator if you need access.</p>

                <div class="mt-6">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Back to Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Sign in
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </body>
</html>
