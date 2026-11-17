<script setup lang="ts">
import TheStatusBadge from '@/components/ui/TheStatusBadge.vue';
import type { Generation } from '@/types';
import { formatDateTime } from '@/utils/date';
import { formatCost } from '@/utils/money';
import { Link } from '@inertiajs/vue3';

defineProps<{
    generation: Generation;
}>();
</script>

<template>
    <tr class="transition hover:bg-white/5">
        <td class="px-4 py-3 text-muted">
            <Link
                :href="`/generations/${generation.id}`"
                class="font-medium text-ink hover:text-accent"
            >
                #{{ generation.id }}
            </Link>
        </td>
        <td class="max-w-md px-4 py-3">
            <Link
                :href="`/generations/${generation.id}`"
                class="line-clamp-2 text-accent underline-offset-2 transition hover:underline"
            >
                {{ generation.prompt }}
            </Link>
        </td>
        <td class="px-4 py-3">
            <TheStatusBadge :status="generation.status" />
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-muted">
            {{ generation.size }}
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-muted">
            {{ formatCost(generation.actual_customer_cost ?? generation.estimated_customer_cost) }}
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-muted">
            {{ formatDateTime(generation.submitted_at ?? generation.created_at) }}
        </td>
    </tr>
</template>
