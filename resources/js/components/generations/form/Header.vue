<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-semibold tracking-tight text-ink">{{ title }}</h1>
        <p class="mt-2 text-sm text-muted">
            {{ description }}
        </p>
    </div>
</template>
