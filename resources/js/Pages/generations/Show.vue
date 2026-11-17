<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser, Generation } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

defineProps<{
    auth: {
        user: AuthUser | null;
    };
    generation: Generation;
}>();
</script>

<template>
    <Head :title="`Generation #${generation.id}`" />

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Generation #{{ generation.id }}</h1>
            <p class="mt-1 text-sm uppercase tracking-wide text-ink/50">{{ generation.status }}</p>
        </div>

        <Link href="/generations">
            <TheButton type="button">History</TheButton>
        </Link>
    </div>

    <div class="max-w-2xl space-y-4 text-sm text-ink">
        <p class="whitespace-pre-wrap">{{ generation.prompt }}</p>

        <dl class="grid grid-cols-2 gap-3 text-ink/70">
            <div>
                <dt class="text-xs uppercase tracking-wide text-ink/40">Size</dt>
                <dd>{{ generation.size }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-ink/40">Quality</dt>
                <dd>{{ generation.quality }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-ink/40">Estimated</dt>
                <dd>{{ generation.estimated_customer_cost ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-ink/40">Actual</dt>
                <dd>{{ generation.actual_customer_cost ?? '—' }}</dd>
            </div>
        </dl>

        <p v-if="generation.error_message" class="text-red-400">
            {{ generation.error_message }}
        </p>

        <img
            v-if="generation.image_url"
            :src="generation.image_url"
            :alt="generation.prompt"
            class="max-w-full border border-white/10"
        />
    </div>
</template>
