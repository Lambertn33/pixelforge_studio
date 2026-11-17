import { onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';
import type { GenerationStatus } from '@/types';
import { router } from '@inertiajs/vue3';

const POLL_INTERVAL_MS = 2500;

const isFinished = (status: GenerationStatus): boolean =>
    status === 'succeeded' || status === 'failed';

export const useGenerationPoll = (status: Ref<GenerationStatus>) => {
    const isPolling = ref(false);
    let pollControls: { stop: () => void; start: () => void; destroy: () => void } | null =
        null;

    const stopPolling = (): void => {
        pollControls?.stop();
        pollControls?.destroy();
        pollControls = null;
        isPolling.value = false;
    };

    const startPolling = (): void => {
        if (isFinished(status.value) || pollControls !== null) {
            return;
        }

        isPolling.value = true;
        pollControls = router.poll(
            POLL_INTERVAL_MS,
            {
                only: ['generation'],
            },
            {
                autoStart: true,
            },
        );
    };

    watch(status, (nextStatus) => {
        if (isFinished(nextStatus)) {
            stopPolling();
        }
    });

    onMounted(() => {
        startPolling();
    });

    onBeforeUnmount(() => {
        stopPolling();
    });

    return {
        isPolling,
    };
};
