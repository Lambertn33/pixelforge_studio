<script setup lang="ts">
import { computed } from 'vue';
import TheButton from '@/components/ui/TheButton.vue';
import TheSelect from '@/components/ui/TheSelect.vue';
import TheTextArea from '@/components/ui/TheTextArea.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser, GenerationQuality, GenerationSize } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

const props = defineProps<{
    auth: {
        user: AuthUser | null;
    };
    sizes: GenerationSize[];
    qualities: GenerationQuality[];
}>();

const sizeOptions = computed(() =>
    props.sizes.map((size) => ({
        value: size,
        label: size,
    })),
);

const qualityOptions = computed(() =>
    props.qualities.map((quality) => ({
        value: quality,
        label: quality.charAt(0).toUpperCase() + quality.slice(1),
    })),
);

const form = useForm({
    prompt: '',
    size: (props.sizes[0] ?? '1024x1024') as GenerationSize,
    quality: (props.qualities.includes('medium')
        ? 'medium'
        : (props.qualities[0] ?? 'medium')) as GenerationQuality,
    reference_images: [] as string[],
});

const submit = () => {
    form.post('/generations');
};
</script>

<template>
    <Head title="Generate" />

    <div class="mx-auto max-w-xl">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-semibold tracking-tight text-ink">Generate</h1>
            <p class="mt-2 text-sm text-muted">
                Write a prompt, choose size and quality, then create your image.
            </p>
        </div>

        <form
            class="space-y-5 rounded-2xl border border-border bg-surface-raised p-6 shadow-[0_12px_40px_rgba(0,0,0,0.35)] sm:p-8"
            @submit.prevent="submit"
        >
            <TheTextArea
                id="prompt"
                v-model="form.prompt"
                label="Prompt"
                required
                :rows="5"
                :maxlength="4000"
                placeholder="Describe the image you want…"
                :error="form.errors.prompt"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <TheSelect
                    id="size"
                    v-model="form.size"
                    label="Size"
                    required
                    :options="sizeOptions"
                    :error="form.errors.size"
                />

                <TheSelect
                    id="quality"
                    v-model="form.quality"
                    label="Quality"
                    required
                    :options="qualityOptions"
                    :error="form.errors.quality"
                />
            </div>

            <div class="flex items-center justify-end pt-2">
                <TheButton type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Submitting…' : 'Generate' }}
                </TheButton>
            </div>
        </form>
    </div>
</template>
