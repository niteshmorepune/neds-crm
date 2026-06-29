@props(['title' => 'NEDS'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
        <p class="text-sm font-semibold text-indigo-700">Niranjan Enterprises Digital Solutions</p>
    </header>
    <main class="mx-auto max-w-2xl px-4 py-10">
        {{ $slot }}
    </main>
</body>
</html>
