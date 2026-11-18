<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import TheInput from '@/components/ui/TheInput.vue';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    q: string;
}>();

const search = ref(props.q);
const isSearching = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.q,
    (value) => {
        search.value = value;
    },
);

const visit = (value: string): void => {
    const next = value.trim();

    if (next === props.q.trim()) {
        return;
    }

    isSearching.value = true;

    router.get(
        '/generations',
        next === '' ? {} : { q: next },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isSearching.value = false;
            },
        },
    );
};

watch(search, (value) => {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        visit(value);
    }, 300);
});

const clear = (): void => {
    search.value = '';

    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    visit('');
};

onBeforeUnmount(() => {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
    }
});
</script>

<template>
    <div class="mb-6 flex flex-wrap items-end gap-3">
        <div class="min-w-[16rem] flex-1">
            <TheInput
                id="generations-search"
                v-model="search"
                type="search"
                label="Search prompts"
                placeholder="Search by prompt… use Typesense and try reversing a word"
                icon="hi-search"
                autocomplete="off"
            />
        </div>

        <TheButton
            v-if="search.length > 0"
            type="button"
            variant="plain"
            class="px-3 py-2.5 text-sm"
            @click="clear"
        >
            Clear
        </TheButton>

        <p
            v-if="isSearching"
            class="w-full text-sm text-muted"
            aria-live="polite"
        >
            Searching…
        </p>
    </div>
</template>
