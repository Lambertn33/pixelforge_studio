<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import TheCheckbox from '@/components/ui/TheCheckbox.vue';
import TheInput from '@/components/ui/TheInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
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
            <TheInput
                id="email"
                v-model="form.email"
                type="email"
                label="Email"
                autocomplete="username"
                required
                autofocus
                icon="hi-mail"
                placeholder="you@example.com"
                :error="form.errors.email"
            />

            <TheInput
                id="password"
                v-model="form.password"
                type="password"
                label="Password"
                autocomplete="current-password"
                required
                icon="hi-lock-closed"
                placeholder="••••••••"
                :error="form.errors.password"
            />

            <TheCheckbox id="remember" v-model="form.remember" label="Remember me" />

            <TheButton type="submit" class="w-full" :disabled="form.processing">
                Log in
            </TheButton>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            No account?
            <Link
                href="/register"
                class="font-medium text-ink underline-offset-4 hover:text-accent hover:underline"
            >
                Register
            </Link>
        </p>
    </GuestLayout>
</template>
