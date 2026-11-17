<script setup lang="ts">
import { computed } from 'vue';
import Details from '@/components/generations/item/Details.vue';
import Header from '@/components/generations/item/Header.vue';
import Preview from '@/components/generations/item/Preview.vue';
import TheBreadcrumb from '@/components/ui/TheBreadcrumb.vue';
import { useGenerationPoll } from '@/composables/useGenerationPoll';
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

const { isPolling } = useGenerationPoll(computed(() => props.generation.status));

const breadcrumbItems = computed(() => [
    { label: 'Generations', href: '/generations' },
    { label: `#${props.generation.id}` },
]);
</script>

<template>
    <Head :title="`Generation #${generation.id}`" />

    <TheBreadcrumb :items="breadcrumbItems" />

    <Header :generation="generation" :is-polling="isPolling" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <Preview :generation="generation" />
        <Details :generation="generation" />
    </div>
</template>
