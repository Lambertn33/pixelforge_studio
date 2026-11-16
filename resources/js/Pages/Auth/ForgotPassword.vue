<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import TheInput from '@/components/ui/TheInput.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { FlashProps } from '@/types/flash';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{
    flash: FlashProps;
    [key: string]: unknown;
}>();

const form = useForm({
    email: '',
});

const submit = (): void => {
    form.post('/forgot-password');
};
</script>

<template>
    <GuestLayout title="Forgot password">
        <h1 class="mb-2 text-xl font-semibold text-ink">Forgot password</h1>
        <p class="mb-6 text-sm text-muted">
            Enter your email and we will send you a password reset link.
        </p>

        <div
            v-if="page.props.flash.status"
            class="mb-4 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-accent"
        >
            {{ page.props.flash.status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <TheInput
                id="email"
                v-model="form.email"
                type="email"
                label="Email"
                required
                autofocus
                icon="hi-mail"
                :error="form.errors.email"
            />

            <TheButton type="submit" class="w-full" :disabled="form.processing">
                Email password reset link
            </TheButton>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            <Link
                href="/login"
                class="inline-flex items-center gap-1 font-medium text-ink underline-offset-4 hover:text-accent hover:underline"
            >
                <VIcon name="hi-chevron-left" scale="0.9" />
                Back to login
            </Link>
        </p>
    </GuestLayout>
</template>
