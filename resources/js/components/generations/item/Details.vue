<script setup lang="ts">
import type { Generation } from '@/types';
import { formatCost } from '@/utils/money';

defineProps<{
    generation: Generation;
}>();
</script>

<template>
    <aside
        class="h-fit space-y-4 rounded-2xl border border-border bg-surface-raised p-5 text-sm shadow-[0_12px_40px_rgba(0,0,0,0.35)]"
    >
        <h2 class="text-xs font-medium uppercase tracking-wide text-muted">Details</h2>
        <dl class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-muted">Size</dt>
                <dd>{{ generation.size }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-muted">Quality</dt>
                <dd class="capitalize">{{ generation.quality }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-muted">Estimated</dt>
                <dd>{{ formatCost(generation.estimated_customer_cost) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-muted">Actual</dt>
                <dd>{{ formatCost(generation.actual_customer_cost) }}</dd>
            </div>
        </dl>

        <p
            v-if="generation.error_message"
            class="rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-danger"
        >
            {{ generation.error_message }}
        </p>
    </aside>
</template>
