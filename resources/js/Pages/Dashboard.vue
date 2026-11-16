<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    auth: {
        user: AuthUser | null;
    };
}>();
</script>

<template>
    <AppLayout title="Dashboard">
        <p class="mb-3 text-xs font-medium uppercase tracking-[0.2em] text-muted">
            Studio
        </p>
        <h1 class="text-3xl font-semibold tracking-tight text-ink">
            Welcome back, {{ auth.user?.name }}
        </h1>
        <p class="mt-3 max-w-2xl text-muted">
            Generate images, track progress, and review costs — all in one place.
        </p>

        <div class="mt-10 grid gap-0 overflow-hidden rounded-2xl border border-border md:grid-cols-3">
            <div class="border-border p-6 md:border-r">
                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-accent text-accent-ink">
                    <VIcon name="hi-plus" scale="1.1" />
                </div>
                <h2 class="text-lg font-semibold text-ink">Create</h2>
                <p class="mt-2 text-sm text-muted">
                    Write a prompt, pick size and quality, then generate.
                </p>
            </div>
            <div class="border-border p-6 md:border-r">
                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-accent text-accent-ink">
                    <VIcon name="hi-photograph" scale="1.1" />
                </div>
                <h2 class="text-lg font-semibold text-ink">Review</h2>
                <p class="mt-2 text-sm text-muted">
                    Follow queued and processing jobs until images are ready.
                </p>
            </div>
            <div class="p-6">
                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-accent text-accent-ink">
                    <VIcon name="hi-user" scale="1.1" />
                </div>
                <h2 class="text-lg font-semibold text-ink">Account</h2>
                <p class="mt-2 text-sm text-muted">
                    See estimated vs actual costs for every generation.
                </p>
            </div>
        </div>

        <div class="mt-8">
            <Link href="/dashboard">
                <TheButton type="button">
                    <VIcon name="hi-plus" scale="0.95" />
                    Generate image
                </TheButton>
            </Link>
        </div>
    </AppLayout>
</template>
