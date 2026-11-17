<script setup lang="ts">
import type { AuthProps } from '@/types/auth';
import type { FlashProps } from '@/types/flash';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineSlots<{
    default: () => unknown;
}>();

const page = usePage<{
    auth: AuthProps;
    flash: FlashProps;
    [key: string]: unknown;
}>();

const user = computed(() => page.props.auth.user);
const flashError = computed(() => page.props.flash?.error ?? null);

const logout = (): void => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-surface text-ink">
        <header class="border-b border-border bg-surface">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <Link href="/dashboard" class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent text-accent-ink">
                        <VIcon name="hi-photograph" scale="1" />
                    </span>
                    <span class="font-semibold tracking-tight">PixelForge Studio</span>
                </Link>

                <div class="flex items-center gap-4 text-sm">
                    <span class="text-muted">{{ user?.name }}</span>
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-border bg-surface-raised font-medium"
                    >
                        {{ user?.name?.charAt(0)?.toUpperCase() }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 text-muted transition hover:text-ink"
                        @click="logout"
                    >
                        <VIcon name="hi-logout" scale="0.95" />
                        Log out
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            <div
                v-if="flashError"
                class="mb-6 rounded-lg border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger"
                role="alert"
            >
                {{ flashError }}
            </div>

            <slot />
        </main>
    </div>
</template>
