<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
        class?: string;
        variant?: 'primary' | 'plain';
    }>(),
    {
        type: 'button',
        disabled: false,
        class: '',
        variant: 'primary',
    },
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();
</script>

<template>
    <button
        :type="props.type"
        :disabled="props.disabled"
        :class="[
            'inline-flex cursor-pointer items-center justify-center gap-2 rounded-full font-semibold transition disabled:cursor-not-allowed disabled:opacity-60',
            props.variant === 'plain'
                ? 'text-muted hover:text-ink'
                : 'bg-accent px-4 py-2.5 text-sm text-accent-ink hover:brightness-110',
            props.class,
        ]"
        @click="emit('click', $event)"
    >
        <slot />
    </button>
</template>
