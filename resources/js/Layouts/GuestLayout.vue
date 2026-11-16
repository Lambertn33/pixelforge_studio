<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
}>();

defineSlots<{
    default: () => unknown;
}>();
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-surface text-ink">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-12">
            <Link href="/" class="mb-8 flex items-center justify-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent text-accent-ink">
                    <VIcon name="hi-photograph" scale="1.1" />
                </span>
                <span class="text-xl font-semibold tracking-tight">PixelForge Studio</span>
            </Link>

            <div class="rounded-2xl border border-border bg-surface-raised p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
