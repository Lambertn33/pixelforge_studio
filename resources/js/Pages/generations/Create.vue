<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuthUser, GenerationQuality, GenerationSize } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-ink">Generate</h1>
        <p class="mt-1 text-sm text-ink/60">
            Submit a prompt. Live estimate and richer form controls come next.
        </p>
    </div>

    <form class="max-w-xl space-y-4" @submit.prevent="submit">
        <div>
            <label for="prompt" class="mb-1.5 block text-sm font-medium text-ink">Prompt</label>
            <textarea
                id="prompt"
                v-model="form.prompt"
                rows="5"
                required
                maxlength="4000"
                class="w-full border border-white/15 bg-transparent px-3 py-2 text-sm text-ink outline-none focus:border-accent"
                placeholder="Describe the image you want…"
            />
            <p v-if="form.errors.prompt" class="mt-1 text-sm text-red-400">
                {{ form.errors.prompt }}
            </p>
        </div>

        <div>
            <label for="size" class="mb-1.5 block text-sm font-medium text-ink">Size</label>
            <select
                id="size"
                v-model="form.size"
                class="w-full border border-white/15 bg-[#1f1f1f] px-3 py-2 text-sm text-ink outline-none focus:border-accent"
            >
                <option v-for="size in sizes" :key="size" :value="size">{{ size }}</option>
            </select>
            <p v-if="form.errors.size" class="mt-1 text-sm text-red-400">{{ form.errors.size }}</p>
        </div>

        <div>
            <label for="quality" class="mb-1.5 block text-sm font-medium text-ink">Quality</label>
            <select
                id="quality"
                v-model="form.quality"
                class="w-full border border-white/15 bg-[#1f1f1f] px-3 py-2 text-sm text-ink outline-none focus:border-accent"
            >
                <option v-for="quality in qualities" :key="quality" :value="quality">
                    {{ quality }}
                </option>
            </select>
            <p v-if="form.errors.quality" class="mt-1 text-sm text-red-400">
                {{ form.errors.quality }}
            </p>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <TheButton type="submit" :disabled="form.processing">
                {{ form.processing ? 'Submitting…' : 'Generate' }}
            </TheButton>
            <Link href="/generations" class="text-sm text-ink/60 hover:text-ink">History</Link>
        </div>
    </form>
</template>
