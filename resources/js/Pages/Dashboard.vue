<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

defineProps<{
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
}>();

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-zinc-50 text-zinc-900">
        <header class="border-b border-zinc-200 bg-white">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-6">
                <div class="font-semibold tracking-tight">PixelForge Studio</div>
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-zinc-600">{{ auth.user?.name }}</span>
                    <button
                        type="button"
                        class="font-medium underline-offset-4 hover:underline"
                        @click="logout"
                    >
                        Log out
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            <h1 class="text-2xl font-semibold tracking-tight">
                Welcome, {{ auth.user?.name }}
            </h1>
            <p class="mt-2 text-zinc-600">
                Inertia + Vue is wired. Generation UI comes next.
            </p>
        </main>
    </div>
</template>
