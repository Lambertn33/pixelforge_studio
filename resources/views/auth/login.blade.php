<x-layouts.guest :title="'Log in — '.config('app.name')">
    <h1 class="mb-6 text-xl font-semibold">Log in</h1>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900"
            >
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900"
            >
        </div>

        <label class="flex items-center gap-2 text-sm text-zinc-600">
            <input type="checkbox" name="remember" class="rounded border-zinc-300">
            Remember me
        </label>

        <button type="submit" class="w-full rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">
            Log in
        </button>
    </form>

    <div class="mt-6 space-y-2 text-center text-sm text-zinc-600">
        <p>
            <a href="{{ route('password.request') }}" class="font-medium text-zinc-900 underline-offset-4 hover:underline">
                Forgot your password?
            </a>
        </p>
        <p>
            No account?
            <a href="{{ route('register') }}" class="font-medium text-zinc-900 underline-offset-4 hover:underline">
                Register
            </a>
        </p>
    </div>
</x-layouts.guest>
