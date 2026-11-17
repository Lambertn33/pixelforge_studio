<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import TheBreadcrumb from '@/components/ui/TheBreadcrumb.vue';
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

type EstimateResponse = {
    currency: string;
    customer_cost: string;
    provider_cost: string;
};

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

const estimate = ref<EstimateResponse | null>(null);
const estimating = ref(false);
const estimateError = ref<string | null>(null);

let estimateTimer: ReturnType<typeof setTimeout> | null = null;
let estimateRequestId = 0;
let estimateAbort: AbortController | null = null;

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const clearEstimate = (): void => {
    estimate.value = null;
    estimating.value = false;
    estimateError.value = null;
};

const fetchEstimate = async (): Promise<void> => {
    const prompt = form.prompt.trim();

    if (prompt === '') {
        clearEstimate();

        return;
    }

    estimateAbort?.abort();
    estimateAbort = new AbortController();
    const requestId = ++estimateRequestId;

    estimating.value = true;
    estimateError.value = null;

    try {
        const response = await fetch('/generations/estimate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            signal: estimateAbort.signal,
            body: JSON.stringify({
                prompt,
                size: form.size,
                quality: form.quality,
                reference_images: form.reference_images,
            }),
        });

        if (requestId !== estimateRequestId) {
            return;
        }

        if (! response.ok) {
            const payload = await response.json().catch(() => null);
            const message =
                payload?.message ??
                payload?.errors?.prompt?.[0] ??
                'Could not estimate cost right now.';

            estimate.value = null;
            estimateError.value = message;
            estimating.value = false;

            return;
        }

        estimate.value = (await response.json()) as EstimateResponse;
        estimating.value = false;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        if (requestId !== estimateRequestId) {
            return;
        }

        estimate.value = null;
        estimateError.value = 'Could not estimate cost right now.';
        estimating.value = false;
    }
};

const scheduleEstimate = (): void => {
    if (estimateTimer !== null) {
        clearTimeout(estimateTimer);
    }

    if (form.prompt.trim() === '') {
        clearEstimate();

        return;
    }

    estimating.value = true;
    estimateError.value = null;
    estimateTimer = setTimeout(() => {
        void fetchEstimate();
    }, 300);
};

watch(
    () => [form.prompt, form.size, form.quality] as const,
    () => {
        scheduleEstimate();
    },
);

onBeforeUnmount(() => {
    if (estimateTimer !== null) {
        clearTimeout(estimateTimer);
    }

    estimateAbort?.abort();
});

const submit = () => {
    form.post('/generations');
};
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
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-semibold tracking-tight text-ink">Create new</h1>
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

            <div
                class="flex items-center justify-between gap-3 rounded-lg border border-border bg-surface px-4 py-3 text-sm"
            >
                <span class="text-muted">Estimated cost</span>
                <span v-if="estimating" class="text-muted">Estimating…</span>
                <span v-else-if="estimateError" class="text-danger">{{ estimateError }}</span>
                <span v-else-if="estimate" class="font-medium text-accent">
                    ${{ estimate.customer_cost }}
                </span>
                <span v-else class="text-muted">—</span>
            </div>

            <div class="flex items-center justify-end pt-2">
                <TheButton type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Submitting…' : 'Generate' }}
                </TheButton>
            </div>
        </form>
    </div>
</template>
