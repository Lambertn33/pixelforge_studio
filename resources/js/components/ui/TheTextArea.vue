<script setup lang="ts">
withDefaults(
    defineProps<{
        id: string;
        name?: string;
        label: string;
        required?: boolean;
        autofocus?: boolean;
        disabled?: boolean;
        error?: string;
        modelValue?: string;
        placeholder?: string;
        rows?: number;
        maxlength?: number;
    }>(),
    {
        required: false,
        autofocus: false,
        disabled: false,
        modelValue: '',
        placeholder: '',
        rows: 4,
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
        <textarea
            :id="id"
            :name="name ?? id"
            :required="required"
            :autofocus="autofocus"
            :disabled="disabled"
            :rows="rows"
            :maxlength="maxlength"
            :value="modelValue"
            :placeholder="placeholder"
            class="w-full resize-y rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-ink outline-none placeholder:text-muted focus:border-accent disabled:cursor-not-allowed disabled:opacity-60"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
        <p v-if="error" class="mt-1 text-sm text-danger">
            {{ error }}
        </p>
    </div>
</template>
