<script setup lang="ts">
import type { UsageReport } from '@/types/usage';
import { formatCost } from '@/utils/money';

defineProps<{
    report: UsageReport;
}>();
</script>

<template>
    <section class="mb-8 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-border bg-surface-raised p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Provider</p>
            <p class="mt-3 text-2xl font-semibold">{{ formatCost(report.provider.cost_usd) }}</p>
            <p class="mt-1 text-sm text-muted">
                {{ report.provider.generations.toLocaleString() }} generations
            </p>
        </div>

        <div class="rounded-2xl border border-border bg-surface-raised p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Local</p>
            <p class="mt-3 text-2xl font-semibold">{{ formatCost(report.local.cost_usd) }}</p>
            <p class="mt-1 text-sm text-muted">
                {{ report.local.generations.toLocaleString() }} generations · customer
                {{ formatCost(report.local.customer_cost_usd ?? null) }}
            </p>
        </div>

        <div class="rounded-2xl border border-border bg-surface-raised p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Difference</p>
            <p class="mt-3 text-2xl font-semibold">{{ formatCost(report.difference_usd) }}</p>
            <p
                class="mt-1 text-sm"
                :class="report.matched ? 'text-success' : 'text-danger'"
            >
                {{ report.matched ? 'Matched' : 'Mismatch' }}
            </p>
        </div>
    </section>
</template>
