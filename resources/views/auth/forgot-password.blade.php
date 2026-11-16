<x-layouts.guest :title="'Forgot password — '.config('app.name')">
    <h1 class="mb-2 text-xl font-semibold">Forgot password</h1>
    <p class="mb-6 text-sm text-zinc-600">
        Enter your email and we will send you a password reset link.
    </p>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
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
                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900"
            >
        </div>

        <button type="submit" class="w-full rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">
            Email password reset link
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-zinc-600">
        <a href="{{ route('login') }}" class="font-medium text-zinc-900 underline-offset-4 hover:underline">
            Back to login
        </a>
    </p>
</x-layouts.guest>
