<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Welcome" />

    <div class="relative min-h-screen overflow-hidden bg-surface text-ink">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(154,230,0,0.12),_transparent_55%)]"
            aria-hidden="true"
        />

        <div class="relative mx-auto flex min-h-screen w-full max-w-3xl flex-col justify-center px-6 py-16">
            <div class="mb-10 flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-accent text-accent-ink">
                    <VIcon name="hi-photograph" scale="1.2" />
                </span>
                <span class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    PixelForge Studio
                </span>
            </div>

            <h1 class="max-w-xl text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                Turn prompts into images.
            </h1>

            <p class="mt-4 max-w-lg text-base leading-relaxed text-muted sm:text-lg">
                Estimate cost up front, generate asynchronously, and track every job
                from queue to finished image — with clear pricing along the way.
            </p>

            <div class="mt-10 flex flex-wrap items-center gap-4">
                <Link href="/register">
                    <TheButton type="button" class="px-6 py-3">
                        Get started
                    </TheButton>
                </Link>

                <Link
                    href="/login"
                    class="text-sm font-medium text-muted underline-offset-4 hover:text-ink hover:underline"
                >
                    Log in
                </Link>
            </div>
        </div>
    </div>
</template>
