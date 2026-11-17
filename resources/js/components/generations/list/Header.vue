<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    description: string;
    createButtonText: string;
    createButtonLink: string;
}>();
</script>

<template>
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink">{{ title }}</h1>
            <p class="mt-1 text-sm text-muted">{{ description }}</p>
        </div>

        <Link :href="createButtonLink">
            <TheButton type="button">
                <VIcon name="hi-plus" scale="0.95" />
                {{ createButtonText }}
            </TheButton>
        </Link>
    </div>
</template>
