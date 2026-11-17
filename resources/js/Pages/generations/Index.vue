<script setup lang="ts">
import EmptyState from '@/components/generations/list/EmptyState.vue';
import Header from '@/components/generations/list/Header.vue';
import Table from '@/components/generations/list/Table.vue';
import TheBreadcrumb from '@/components/ui/TheBreadcrumb.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser, Generation, Paginator } from '@/types';
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

defineProps<{
    auth: {
        user: AuthUser | null;
    };
    generations: Paginator<Generation>;
}>();
</script>

<template>
    <Head title="My generations" />

    <TheBreadcrumb
        :items="[
            { label: 'Generations', href: '/generations' },
            { label: 'History' },
        ]"
    />

    <Header
        title="My generations"
        description="Review status, costs, and results."
        createButtonText="Create new"
        createButtonLink="/generations/create"
    />

    <EmptyState v-if="generations.data.length === 0" empty-message="No generations yet." />
    <Table v-else :generations="generations.data" />
</template>
