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
        <header class="border-b border-zinc-200 bg-white">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-6">
                <a href="{{ route('dashboard') }}" class="font-semibold tracking-tight">
                    {{ config('app.name') }}
                </a>

                <div class="flex items-center gap-4 text-sm">
                    <span class="text-zinc-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="font-medium text-zinc-900 underline-offset-4 hover:underline">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            {{ $slot }}
        </main>
    </body>
</html>
