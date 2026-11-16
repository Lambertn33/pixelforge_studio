<script setup lang="ts">
import GuestLayout from '@/layouts/GuestLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const status = computed(() => (page.props.flash as { status?: string | null })?.status);

const form = useForm({
    email: '',
});

const submit = () => {
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
            v-if="status"
            class="mb-4 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-accent"
        >
            {{ status }}
        </div>

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
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 pl-10 text-sm text-ink outline-none placeholder:text-muted focus:border-accent"
                    >
                </div>
                <p v-if="form.errors.email" class="mt-1 text-sm text-danger">
                    {{ form.errors.email }}
                </p>
            </div>

            <button
                type="submit"
                class="w-full rounded-full bg-accent px-4 py-2.5 text-sm font-semibold text-accent-ink transition hover:brightness-110 disabled:opacity-60"
                :disabled="form.processing"
            >
                Email password reset link
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            <Link href="/login" class="inline-flex items-center gap-1 font-medium text-ink underline-offset-4 hover:text-accent hover:underline">
                <VIcon name="hi-chevron-left" scale="0.9" />
                Back to login
            </Link>
        </p>
    </GuestLayout>
</template>
