<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import TheInput from '@/components/ui/TheInput.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    startDate: string;
    endDate: string;
}>();

const startDate = ref(props.startDate);
const endDate = ref(props.endDate);

const submit = (): void => {
    router.get(
        '/usage',
        {
            start_date: startDate.value,
            end_date: endDate.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};
</script>

<template>
    <form class="mb-8 flex flex-wrap items-end gap-4" @submit.prevent="submit">
        <div class="w-full max-w-[12rem]">
            <TheInput
                id="start_date"
                v-model="startDate"
                type="date"
                label="Start (UTC)"
                required
            />
        </div>
        <div class="w-full max-w-[12rem]">
            <TheInput
                id="end_date"
                v-model="endDate"
                type="date"
                label="End (UTC)"
                required
            />
        </div>
        <TheButton type="submit">Update range</TheButton>
    </form>
</template>
