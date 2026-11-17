<script setup lang="ts">
import type { EstimateResponse } from '@/composables/useGenerationEstimate';

defineProps<{
    estimate: EstimateResponse | null;
    estimating: boolean;
    error: string | null;
}>();
</script>

<template>
    <div
        class="flex items-center justify-between gap-3 rounded-lg border border-border bg-surface px-4 py-3 text-sm"
    >
        <span class="text-muted">Estimated cost</span>
        <span v-if="estimating" class="text-muted">Estimating…</span>
        <span v-else-if="error" class="text-danger">{{ error }}</span>
        <span v-else-if="estimate" class="font-medium text-accent">
            ${{ estimate.customer_cost }}
        </span>
        <span v-else class="text-muted">—</span>
    </div>
</template>
