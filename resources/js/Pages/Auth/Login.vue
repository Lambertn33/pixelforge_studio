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
        <h1 class="mb-6 text-xl font-semibold text-ink">Log in</h1>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-ink">Email</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        <VIcon name="hi-mail" scale="0.95" />
                    </span>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 pl-10 text-sm text-ink outline-none placeholder:text-muted focus:border-accent"
                        placeholder="you@example.com"
                    >
                </div>
                <p v-if="form.errors.email" class="mt-1 text-sm text-danger">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-ink">Password</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        <VIcon name="hi-lock-closed" scale="0.95" />
                    </span>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 pl-10 text-sm text-ink outline-none placeholder:text-muted focus:border-accent"
                        placeholder="••••••••"
                    >
                </div>
                <p v-if="form.errors.password" class="mt-1 text-sm text-danger">
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex items-center gap-2 text-sm text-muted">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="rounded border-border bg-surface text-accent focus:ring-accent"
                >
                Remember me
            </label>

            <button
                type="submit"
                class="w-full rounded-full bg-accent px-4 py-2.5 text-sm font-semibold text-accent-ink transition hover:brightness-110 disabled:opacity-60"
                :disabled="form.processing"
            >
                Log in
            </button>
        </form>

        <div class="mt-6 space-y-2 text-center text-sm text-muted">
            <p>
                <Link href="/forgot-password" class="font-medium text-ink underline-offset-4 hover:text-accent hover:underline">
                    Forgot your password?
                </Link>
            </p>
            <p>
                No account?
                <Link href="/register" class="font-medium text-ink underline-offset-4 hover:text-accent hover:underline">
                    Register
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
