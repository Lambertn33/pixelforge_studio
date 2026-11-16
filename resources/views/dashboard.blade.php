<x-layouts.app :title="'Dashboard — '.config('app.name')">
    <h1 class="text-2xl font-semibold tracking-tight">Welcome, {{ auth()->user()->name }}</h1>
    <p class="mt-2 text-zinc-600">
        Authentication is ready. Image generation UI will arrive in a later phase.
    </p>
</x-layouts.app>
