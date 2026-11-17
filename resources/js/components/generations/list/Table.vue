<script setup lang="ts">
import Row from '@/components/generations/list/Row.vue';
import type { Generation } from '@/types';

defineProps<{
    generations: Generation[];
}>();
</script>

<template>
    <div
        class="overflow-x-auto rounded-xl border border-border bg-surface-raised shadow-[0_8px_30px_rgba(0,0,0,0.25)]"
    >
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-border bg-surface/60 text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">ID</th>
                    <th class="px-4 py-3 font-medium">Prompt</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Size</th>
                    <th class="px-4 py-3 font-medium">Cost</th>
                    <th class="px-4 py-3 font-medium">Submitted</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <Row
                    v-for="generation in generations"
                    :key="generation.id"
                    :generation="generation"
                />
            </tbody>
        </table>
    </div>
</template>
