<script setup lang="ts">
import TheButton from '@/components/ui/TheButton.vue';
import TheInput from '@/components/ui/TheInput.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout title="Reset password">
        <h1 class="mb-6 text-xl font-semibold text-ink">Reset password</h1>

        <form class="space-y-4" @submit.prevent="submit">
            <TheInput
                id="email"
                v-model="form.email"
                type="email"
                label="Email"
                required
                autofocus
                :error="form.errors.email"
            />

            <TheInput
                id="password"
                v-model="form.password"
                type="password"
                label="Password"
                autocomplete="new-password"
                required
                :error="form.errors.password"
            />

            <TheInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                label="Confirm password"
                autocomplete="new-password"
                required
            />

            <TheButton type="submit" class="w-full" :disabled="form.processing">
                Reset password
            </TheButton>
        </form>
    </GuestLayout>
</template>
