<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        emptyMessage: string;
        showCreateLink?: boolean;
    }>(),
    {
        showCreateLink: true,
    },
);
</script>

<template>
    <div
        class="rounded-xl border border-border bg-surface-raised px-6 py-10 text-center text-sm text-muted"
    >
        {{ emptyMessage }}
        <Link
            v-if="showCreateLink"
            href="/generations/create"
            class="text-accent underline"
        >
            Create one
        </Link>
    </div>
</template>
