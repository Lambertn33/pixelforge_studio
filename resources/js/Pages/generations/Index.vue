<script setup lang="ts">
import EmptyState from '@/components/generations/list/EmptyState.vue';
import Header from '@/components/generations/list/Header.vue';
import Pagination from '@/components/generations/list/Pagination.vue';
import Search from '@/components/generations/list/Search.vue';
import Table from '@/components/generations/list/Table.vue';
import TheBreadcrumb from '@/components/ui/TheBreadcrumb.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { AuthUser, Generation, Paginator } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: AppLayout,
});

const props = defineProps<{
    auth: {
        user: AuthUser | null;
    };
    generations: Paginator<Generation>;
    filters: {
        q: string;
    };
}>();

const isSearching = computed(() => props.filters.q.trim().length > 0);

const emptyMessage = computed(() =>
    isSearching.value
        ? 'No generations match your search.'
        : 'No generations yet.',
);
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

    <Search :q="filters.q" />

    <EmptyState
        v-if="generations.data.length === 0"
        :empty-message="emptyMessage"
        :show-create-link="!isSearching"
    />
    <template v-else>
        <Table :generations="generations.data" />
        <Pagination :paginator="generations" />
    </template>
</template>
