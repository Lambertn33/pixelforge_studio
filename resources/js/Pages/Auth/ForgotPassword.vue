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
        <h1 class="mb-2 text-xl font-semibold">Forgot password</h1>
        <p class="mb-6 text-sm text-zinc-600">
            Enter your email and we will send you a password reset link.
        </p>

        <div
            v-if="status"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            {{ status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900"
                >
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                    {{ form.errors.email }}
                </p>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60"
                :disabled="form.processing"
            >
                Email password reset link
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-zinc-600">
            <Link href="/login" class="font-medium text-zinc-900 underline-offset-4 hover:underline">
                Back to login
            </Link>
        </p>
    </GuestLayout>
</template>
