<script setup lang="ts">
import GenerationForm from '@/components/generations/form/GenerationForm.vue';
import Header from '@/components/generations/form/Header.vue';
import TheBreadcrumb from '@/components/ui/TheBreadcrumb.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { AuthUser, GenerationQuality, GenerationSize } from '@/types';
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

defineProps<{
    auth: {
        user: AuthUser | null;
    };
    sizes: GenerationSize[];
    qualities: GenerationQuality[];
}>();
</script>

<template>
    <Head title="Create generation" />

    <TheBreadcrumb
        :items="[
            { label: 'Generations', href: '/generations' },
            { label: 'Create new' },
        ]"
    />

    <div class="mx-auto max-w-xl">
        <Header
            title="Generate an image"
            description="Write a prompt, choose size and quality, then generate your image."
        />
        <GenerationForm :sizes="sizes" :qualities="qualities" />
    </div>
</template>
