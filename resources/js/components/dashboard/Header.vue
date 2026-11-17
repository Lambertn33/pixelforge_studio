<script setup lang="ts">
defineProps<{
    username: string;
    title: string;
    description: string;
}>();
</script>

<template>
    <p class="mb-3 text-xs font-medium uppercase tracking-[0.2em] text-muted">
            {{ title }}
        </p>
        <h1 class="text-3xl font-semibold tracking-tight text-ink">
            Welcome back, <span class="text-accent ml-1">{{ username }}</span>
        </h1>
        <p class="mt-3 max-w-2xl text-muted">
            {{ description }}
        </p>
</template>