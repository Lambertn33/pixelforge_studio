<script setup lang="ts">
import type { UsageDayRow } from '@/types/usage';
import { formatCost } from '@/utils/money';

defineProps<{
    days: UsageDayRow[];
}>();
</script>

<template>
    <section class="overflow-x-auto rounded-2xl border border-border">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-border bg-surface-raised text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Date (UTC)</th>
                    <th class="px-4 py-3 font-medium">Provider gens</th>
                    <th class="px-4 py-3 font-medium">Local gens</th>
                    <th class="px-4 py-3 font-medium">Provider cost</th>
                    <th class="px-4 py-3 font-medium">Local cost</th>
                    <th class="px-4 py-3 font-medium">Diff</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="day in days"
                    :key="day.date"
                    class="border-b border-border/70 last:border-b-0"
                >
                    <td class="px-4 py-3">{{ day.date }}</td>
                    <td class="px-4 py-3">{{ day.provider_generations }}</td>
                    <td class="px-4 py-3">{{ day.local_generations }}</td>
                    <td class="px-4 py-3">{{ formatCost(day.provider_cost_usd) }}</td>
                    <td class="px-4 py-3">{{ formatCost(day.local_cost_usd) }}</td>
                    <td class="px-4 py-3">{{ formatCost(day.difference_usd) }}</td>
                    <td class="px-4 py-3">
                        <span :class="day.matched ? 'text-success' : 'text-danger'">
                            {{ day.matched ? 'Matched' : 'Mismatch' }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
