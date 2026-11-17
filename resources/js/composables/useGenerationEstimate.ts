import { onBeforeUnmount, ref, watch, type Ref } from 'vue';
import type { GenerationQuality, GenerationSize } from '@/types';

export type EstimateResponse = {
    currency: string;
    customer_cost: string;
    provider_cost: string;
};

type EstimateInput = {
    prompt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    reference_images: string[];
};

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

export const useGenerationEstimate = (input: Ref<EstimateInput>) => {
    const estimate = ref<EstimateResponse | null>(null);
    const estimating = ref(false);
    const estimateError = ref<string | null>(null);

    let estimateTimer: ReturnType<typeof setTimeout> | null = null;
    let estimateRequestId = 0;
    let estimateAbort: AbortController | null = null;

    const clearEstimate = (): void => {
        estimate.value = null;
        estimating.value = false;
        estimateError.value = null;
    };

    const fetchEstimate = async (): Promise<void> => {
        const prompt = input.value.prompt.trim();

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
                    size: input.value.size,
                    quality: input.value.quality,
                    reference_images: input.value.reference_images,
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

        if (input.value.prompt.trim() === '') {
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
        input,
        () => {
            scheduleEstimate();
        },
        { deep: true },
    );

    onBeforeUnmount(() => {
        if (estimateTimer !== null) {
            clearTimeout(estimateTimer);
        }

        estimateAbort?.abort();
    });

    return {
        estimate,
        estimating,
        estimateError,
    };
};
