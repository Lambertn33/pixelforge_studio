<x-layouts.guest :title="'Reset password — '.config('app.name')">
    <h1 class="mb-6 text-xl font-semibold">Reset password</h1>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $request->email) }}"
                required
                autofocus
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
                autocomplete="new-password"
                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900"
            >
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900"
            >
        </div>

        <button type="submit" class="w-full rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">
            Reset password
        </button>
    </form>
</x-layouts.guest>
