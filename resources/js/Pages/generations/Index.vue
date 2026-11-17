<script setup lang="ts">
import TheBreadcrumb from '@/components/ui/TheBreadcrumb.vue';
import TheButton from '@/components/ui/TheButton.vue';
import TheStatusBadge from '@/components/ui/TheStatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser, Generation, Paginator } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

defineProps<{
    auth: {
        user: AuthUser | null;
    };
    generations: Paginator<Generation>;
}>();

const formatCost = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return `$${value}`;
};

const formatDate = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <Head title="My generations" />

    <TheBreadcrumb
        :items="[
            { label: 'Generations', href: '/generations' },
            { label: 'History' },
        ]"
    />

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink">My generations</h1>
            <p class="mt-1 text-sm text-muted">Review status, costs, and results.</p>
        </div>

        <Link href="/generations/create">
            <TheButton type="button">
                <VIcon name="hi-plus" scale="0.95" />
                Create new
            </TheButton>
        </Link>
    </div>

    <div
        v-if="generations.data.length === 0"
        class="rounded-xl border border-border bg-surface-raised px-6 py-10 text-center text-sm text-muted"
    >
        No generations yet.
        <Link href="/generations/create" class="text-accent underline">Create one</Link>
    </div>

    <div v-else class="overflow-x-auto rounded-xl border border-border bg-surface-raised shadow-[0_8px_30px_rgba(0,0,0,0.25)]">
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
                <tr
                    v-for="generation in generations.data"
                    :key="generation.id"
                    class="transition hover:bg-white/5"
                >
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
                        {{ formatDate(generation.submitted_at ?? generation.created_at) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
