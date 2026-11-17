<script setup lang="ts">
import { computed } from 'vue';
import TheBreadcrumb from '@/components/ui/TheBreadcrumb.vue';
import TheStatusBadge from '@/components/ui/TheStatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser, Generation } from '@/types';
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

const props = defineProps<{
    auth: {
        user: AuthUser | null;
    };
    generation: Generation;
}>();

const breadcrumbItems = computed(() => [
    { label: 'Generations', href: '/generations' },
    { label: `#${props.generation.id}` },
]);

const formatCost = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return `$${value}`;
};
</script>

<template>
    <Head :title="`Generation #${generation.id}`" />

    <TheBreadcrumb :items="breadcrumbItems" />

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold text-ink">Generation #{{ generation.id }}</h1>
                <TheStatusBadge :status="generation.status" />
            </div>
            <p class="mt-2 max-w-2xl text-sm text-ink/80">{{ generation.prompt }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section
            class="overflow-hidden rounded-2xl border border-border bg-surface-raised shadow-[0_12px_40px_rgba(0,0,0,0.35)]"
        >
            <div
                v-if="generation.image_url"
                class="bg-surface"
            >
                <img
                    :src="generation.image_url"
                    :alt="generation.prompt"
                    class="mx-auto max-h-[36rem] w-full object-contain"
                />
            </div>
            <div
                v-else
                class="flex min-h-72 items-center justify-center px-6 text-center text-sm text-muted"
            >
                <p v-if="generation.status === 'failed'">
                    {{ generation.error_message ?? 'This generation failed.' }}
                </p>
                <p v-else-if="generation.status === 'queued' || generation.status === 'processing'">
                    Image preview will appear when this generation finishes.
                </p>
                <p v-else>No image available.</p>
            </div>
        </section>

        <aside class="h-fit space-y-4 rounded-2xl border border-border bg-surface-raised p-5 text-sm shadow-[0_12px_40px_rgba(0,0,0,0.35)]">
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

            <p v-if="generation.error_message" class="rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-danger">
                {{ generation.error_message }}
            </p>
        </aside>
    </div>
</template>
