<script setup lang="ts">
import TheStatusBadge from '@/components/ui/TheStatusBadge.vue';
import type { Generation } from '@/types';

defineProps<{
    generation: Generation;
    isPolling?: boolean;
}>();
</script>

<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold text-ink">Generation #{{ generation.id }}</h1>
                <TheStatusBadge :status="generation.status" />
                <span v-if="isPolling" class="text-xs text-muted">Checking status…</span>
            </div>
            <p class="mt-2 max-w-2xl text-sm text-ink/80">{{ generation.prompt }}</p>
        </div>
    </div>
</template>
