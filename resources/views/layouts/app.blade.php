<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'NEDS CRM') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 flex">
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col min-w-0">
                <!-- Top bar -->
                <header class="bg-white shadow-sm">
                    <div class="h-16 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                        <div class="font-semibold text-gray-800">
                            @isset($header)
                                {{ $header }}
                            @endisset
                        </div>

                        <form method="GET" action="{{ route('search') }}" class="hidden flex-1 px-6 md:block">
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search clients, leads, deals, invoices, tickets…"
                                   class="w-full max-w-md rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </form>

                        <div class="flex items-center">
                        @if (Auth::user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Manager, \App\Enums\UserRole::Sales, \App\Enums\UserRole::Support))
                            <a href="{{ route('calls.create') }}" class="mr-4 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                ☎ Log a call
                            </a>
                        @endif

                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-600 bg-white hover:text-gray-900 focus:outline-none transition">
                                    <div class="text-left">
                                        <div>{{ Auth::user()->name }}</div>
                                        <div class="text-xs text-gray-400">{{ Auth::user()->role->label() }}</div>
                                    </div>
                                    <svg class="fill-current h-4 w-4 ms-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('Profile') }}
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault(); this.closest('form').submit();">
                                        {{ __('Log Out') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
