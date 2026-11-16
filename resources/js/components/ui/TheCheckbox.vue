<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        id: string;
        name?: string;
        label: string;
        value?: string;
        modelValue?: boolean;
        class?: string;
    }>(),
    {
        modelValue: false,
        class: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>

<template>
    <label
        :for="props.id"
        :class="['flex items-center gap-2 text-sm text-muted', props.class]"
    >
        <input
            :id="props.id"
            :name="props.name ?? props.id"
            type="checkbox"
            :value="props.value"
            :checked="props.modelValue"
            class="rounded border-border bg-surface text-accent focus:ring-accent"
            @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
        >
        {{ props.label }}
    </label>
</template>
