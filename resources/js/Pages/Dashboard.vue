<script setup lang="ts">
import { ref } from 'vue';
import DashboardCards from '@/components/dashboard/Cards.vue';
import DashboardHeader from '@/components/dashboard/Header.vue';
import TheButton from '@/components/ui/TheButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser, DashboardCard } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

defineProps<{
    auth: {
        user: AuthUser | null;
    };
}>();

const cards = ref<DashboardCard[]>([
    {
        title: 'Create',
        description: 'Write a prompt, pick size and quality, then generate.',
        icon: 'hi-plus',
    },
    {
        title: 'Review',
        description: 'Follow queued and processing jobs until images are ready.',
        icon: 'hi-photograph',
    },
    {
        title: 'Account',
        description: 'See estimated vs actual costs for every generation.',
        icon: 'hi-user',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <DashboardHeader
        :username="auth.user?.name ?? ''"
        title="Studio"
        description="Generate images, track progress, and review costs — all in one place."
    />

    <DashboardCards :cards="cards" />

    <div class="mt-8">
        <div class="flex flex-wrap gap-3">
            <Link href="/generations/create">
                <TheButton type="button">
                    <VIcon name="hi-plus" scale="0.95" />
                    Generate image
                </TheButton>
            </Link>
            <Link href="/generations" class="text-sm text-ink/60 hover:text-ink self-center">
                View history
            </Link>
        </div>
    </div>
</template>
