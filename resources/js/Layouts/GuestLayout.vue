<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
}>();
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-12">
            <Link href="/" class="mb-8 text-center text-2xl font-semibold tracking-tight">
                PixelForge Studio
            </Link>

            <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
                <slot />
            </div>
        </div>
    </div>
</template>
