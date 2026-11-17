<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    emptyMessage: string;
}>();
</script>

<template>
    <div
        class="rounded-xl border border-border bg-surface-raised px-6 py-10 text-center text-sm text-muted"
    >
        {{ emptyMessage }}
        <Link href="/generations/create" class="text-accent underline">Create one</Link>
    </div>
</template>
