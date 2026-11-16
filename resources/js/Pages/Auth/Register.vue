<script setup lang="ts">
import GuestLayout from '@/layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout title="Register">
        <h1 class="mb-6 text-xl font-semibold text-ink">Create an account</h1>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-ink">Name</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        <VIcon name="hi-user" scale="0.95" />
                    </span>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 pl-10 text-sm text-ink outline-none placeholder:text-muted focus:border-accent"
                    >
                </div>
                <p v-if="form.errors.name" class="mt-1 text-sm text-danger">
                    {{ form.errors.name }}
                </p>
            </div>

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
                        autocomplete="username"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 pl-10 text-sm text-ink outline-none placeholder:text-muted focus:border-accent"
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
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 pl-10 text-sm text-ink outline-none placeholder:text-muted focus:border-accent"
                    >
                </div>
                <p v-if="form.errors.password" class="mt-1 text-sm text-danger">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-ink">Confirm password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-ink outline-none placeholder:text-muted focus:border-accent"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-full bg-accent px-4 py-2.5 text-sm font-semibold text-accent-ink transition hover:brightness-110 disabled:opacity-60"
                :disabled="form.processing"
            >
                Register
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            Already registered?
            <Link href="/login" class="font-medium text-ink underline-offset-4 hover:text-accent hover:underline">
                Log in
            </Link>
        </p>
    </GuestLayout>
</template>
