<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export type BreadcrumbItem = {
    label: string;
    href?: string;
};

defineProps<{
    items: BreadcrumbItem[];
}>();
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-4">
        <ol class="flex flex-wrap items-center gap-2 text-sm text-muted">
            <li v-for="(item, index) in items" :key="`${item.label}-${index}`" class="flex items-center gap-2">
                <span v-if="index > 0" class="text-muted/40" aria-hidden="true">/</span>
                <Link
                    v-if="item.href"
                    :href="item.href"
                    class="transition hover:text-ink"
                >
                    {{ item.label }}
                </Link>
                <span v-else class="text-ink" aria-current="page">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
