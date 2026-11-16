<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import TheInput from '@/components/ui/TheInput.vue';
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
            <TheInput
                id="name"
                v-model="form.name"
                label="Name"
                autocomplete="name"
                required
                autofocus
                icon="hi-user"
                :error="form.errors.name"
            />

            <TheInput
                id="email"
                v-model="form.email"
                type="email"
                label="Email"
                autocomplete="username"
                required
                icon="hi-mail"
                :error="form.errors.email"
            />

            <TheInput
                id="password"
                v-model="form.password"
                type="password"
                label="Password"
                autocomplete="new-password"
                required
                icon="hi-lock-closed"
                :error="form.errors.password"
            />

            <TheInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                label="Confirm password"
                autocomplete="new-password"
                required
            />

            <TheButton type="submit" class="w-full" :disabled="form.processing">
                Register
            </TheButton>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            Already registered?
            <Link
                href="/login"
                class="font-medium text-ink underline-offset-4 hover:text-accent hover:underline"
            >
                Log in
            </Link>
        </p>
    </GuestLayout>
</template>
