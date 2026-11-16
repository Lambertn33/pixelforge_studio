<script setup lang="ts">
import { onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

function close(): void {
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.open) {
        close();
    }
}

watch(
    () => props.open,
    (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
    },
);

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
        >
            <button
                type="button"
                class="absolute inset-0 cursor-pointer bg-black/60"
                aria-label="Close dialog"
                @click="close"
            />

            <div class="relative w-full max-w-md rounded-2xl border border-border bg-surface-raised p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-semibold tracking-tight text-ink">
                        {{ title }}
                    </h2>

                    <button
                        type="button"
                        class="cursor-pointer rounded-lg px-2 py-1 text-sm text-muted transition hover:bg-surface hover:text-ink"
                        aria-label="Close"
                        @click="close"
                    >
                        Esc
                    </button>
                </div>

                <div class="mt-5">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
