<script setup lang="ts">
import type { Generation } from '@/types';

defineProps<{
    generation: Generation;
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-border bg-surface-raised shadow-[0_12px_40px_rgba(0,0,0,0.35)]"
    >
        <div v-if="generation.image_url" class="bg-surface">
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
</template>
