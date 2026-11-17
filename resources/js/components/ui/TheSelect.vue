<script setup lang="ts">
export type SelectOption = {
    value: string;
    label: string;
};

withDefaults(
    defineProps<{
        id: string;
        name?: string;
        label: string;
        options: SelectOption[];
        required?: boolean;
        disabled?: boolean;
        error?: string;
        modelValue?: string;
    }>(),
    {
        required: false,
        disabled: false,
        modelValue: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div>
        <label :for="id" class="mb-1 block text-sm font-medium text-ink">
            {{ label }}
        </label>
        <select
            :id="id"
            :name="name ?? id"
            :required="required"
            :disabled="disabled"
            :value="modelValue"
            class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-ink outline-none focus:border-accent disabled:cursor-not-allowed disabled:opacity-60"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
        <p v-if="error" class="mt-1 text-sm text-danger">
            {{ error }}
        </p>
    </div>
</template>
