<script setup lang="ts">
import type { Paginator } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    paginator: Paginator;
}>();
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        class="mt-6 flex flex-wrap items-center justify-center gap-1"
        aria-label="Pagination"
    >
        <template v-for="(link, index) in paginator.links" :key="`${link.label}-${index}`">
            <Link
                v-if="link.url"
                :href="link.url"
                class="rounded-lg px-3 py-1.5 text-sm transition"
                :class="
                    link.active
                        ? 'bg-accent font-semibold text-accent-ink'
                        : 'text-muted hover:bg-surface-raised hover:text-ink'
                "
                preserve-scroll
                v-html="link.label"
            />
            <span
                v-else
                class="rounded-lg px-3 py-1.5 text-sm text-muted/50"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
