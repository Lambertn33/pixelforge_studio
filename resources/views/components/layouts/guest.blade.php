@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-12">
            <a href="{{ url('/') }}" class="mb-8 text-center text-2xl font-semibold tracking-tight">
                {{ config('app.name') }}
            </a>

            <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
