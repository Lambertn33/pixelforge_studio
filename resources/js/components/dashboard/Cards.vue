

<script setup lang="ts">
import type { DashboardCard } from '@/types';

defineProps<{
    cards: DashboardCard[];
}>();
</script>

<template>
    <div class="mt-10 grid gap-0 overflow-hidden rounded-2xl border border-border md:grid-cols-3">
        <div
            v-for="(card, index) in cards"
            :key="card.title"
            :class="[
                'p-6',
                index < cards.length - 1 ? 'border-border md:border-r' : '',
            ]"
        >
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-accent text-accent-ink">
                <VIcon :name="card.icon" scale="1.1" />
            </div>
            <h2 class="text-lg font-semibold text-ink">
                {{ card.title }}
            </h2>
            <p class="mt-2 text-sm text-muted">
                {{ card.description }}
            </p>
        </div>
    </div>
</template>
