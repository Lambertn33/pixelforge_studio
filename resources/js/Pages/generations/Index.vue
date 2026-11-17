<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser, Generation, Paginator } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

defineProps<{
    auth: {
        user: AuthUser | null;
    };
    generations: Paginator<Generation>;
}>();
</script>

<template>
    <Head title="History" />

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink">History</h1>
            <p class="mt-1 text-sm text-ink/60">Your recent image generations.</p>
        </div>

        <Link href="/generations/create">
            <TheButton type="button">
                <VIcon name="hi-plus" scale="0.95" />
                Generate
            </TheButton>
        </Link>
    </div>

    <div v-if="generations.data.length === 0" class="text-sm text-ink/60">
        No generations yet.
        <Link href="/generations/create" class="text-accent underline">Create one</Link>
    </div>

    <ul v-else class="divide-y divide-white/10 border border-white/10">
        <li v-for="generation in generations.data" :key="generation.id">
            <Link
                :href="`/generations/${generation.id}`"
                class="block px-4 py-3 transition hover:bg-white/5"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="line-clamp-2 text-sm text-ink">{{ generation.prompt }}</p>
                    <span class="shrink-0 text-xs uppercase tracking-wide text-ink/50">
                        {{ generation.status }}
                    </span>
                </div>
            </Link>
        </li>
    </ul>
</template>
