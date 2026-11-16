<script setup lang="ts">
import GuestLayout from '@/layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout title="Log in">
        <h1 class="mb-6 text-xl font-semibold">Log in</h1>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900"
                >
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900"
                >
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex items-center gap-2 text-sm text-zinc-600">
                <input v-model="form.remember" type="checkbox" class="rounded border-zinc-300">
                Remember me
            </label>

            <button
                type="submit"
                class="w-full rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60"
                :disabled="form.processing"
            >
                Log in
            </button>
        </form>

        <div class="mt-6 space-y-2 text-center text-sm text-zinc-600">
            <p>
                <Link href="/forgot-password" class="font-medium text-zinc-900 underline-offset-4 hover:underline">
                    Forgot your password?
                </Link>
            </p>
            <p>
                No account?
                <Link href="/register" class="font-medium text-zinc-900 underline-offset-4 hover:underline">
                    Register
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
