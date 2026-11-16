<script setup lang="ts">
withDefaults(
    defineProps<{
        id: string;
        name?: string;
        label: string;
        type?:
            | 'text'
            | 'email'
            | 'password'
            | 'number'
            | 'tel'
            | 'url'
            | 'search'
            | 'date'
            | 'time'
            | 'datetime-local'
            | 'month'
            | 'week';
        autocomplete?: string;
        required?: boolean;
        autofocus?: boolean;
        error?: string;
        modelValue?: string;
        placeholder?: string;
        icon?: string;
    }>(),
    {
        type: 'text',
        required: false,
        autofocus: false,
        modelValue: '',
        placeholder: '',
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
        <div class="relative">
            <span
                v-if="icon"
                class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted"
            >
                <VIcon :name="icon" scale="0.95" />
            </span>
            <input
                :id="id"
                :name="name ?? id"
                :type="type"
                :autocomplete="autocomplete"
                :required="required"
                :autofocus="autofocus"
                :value="modelValue"
                :placeholder="placeholder"
                :class="[
                    'w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-ink outline-none placeholder:text-muted focus:border-accent',
                    icon ? 'pl-10' : '',
                ]"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            >
        </div>
        <p v-if="error" class="mt-1 text-sm text-danger">
            {{ error }}
        </p>
    </div>
</template>
