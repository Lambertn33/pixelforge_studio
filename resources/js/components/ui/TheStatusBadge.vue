<script setup lang="ts">
import { computed } from 'vue';
import type { GenerationStatus } from '@/types';

const props = defineProps<{
    status: GenerationStatus;
}>();

const label = computed(() => {
    switch (props.status) {
        case 'succeeded':
            return 'Succeeded';
        case 'failed':
            return 'Failed';
        case 'processing':
            return 'Processing';
        case 'queued':
            return 'Queued';
    }
});

const classes = computed(() => {
    switch (props.status) {
        case 'succeeded':
            return 'border-accent/30 bg-accent/15 text-accent';
        case 'failed':
            return 'border-danger/30 bg-danger/15 text-danger';
        case 'processing':
            return 'border-amber-400/30 bg-amber-400/10 text-amber-300';
        case 'queued':
            return 'border-border bg-surface text-muted';
    }
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium',
            classes,
        ]"
    >
        {{ label }}
    </span>
</template>
