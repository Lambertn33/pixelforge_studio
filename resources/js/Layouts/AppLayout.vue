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
const currentPath = computed(() => page.url.split('?')[0] ?? page.url);

const navLinks = [
    { href: '/generations/create', label: 'Start generating', match: '/generations/create' },
    { href: '/generations', label: 'My generations', match: '/generations' },
] as const;

const isActive = (match: string): boolean => {
    if (match === '/generations') {
        return currentPath.value === '/generations' || /^\/generations\/\d+$/.test(currentPath.value);
    }

    return currentPath.value === match || currentPath.value.startsWith(`${match}/`);
};

const logout = (): void => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-surface text-ink">
        <header class="border-b border-border bg-surface">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-6">
                <Link href="/dashboard" class="flex shrink-0 items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent text-accent-ink">
                        <VIcon name="hi-photograph" scale="1" />
                    </span>
                    <span class="font-semibold tracking-tight">PixelForge Studio</span>
                </Link>

                <nav class="hidden items-center gap-1 sm:flex" aria-label="Primary">
                    <Link
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        :class="[
                            'rounded-lg px-3 py-2 text-sm transition',
                            isActive(link.match)
                                ? 'bg-surface-raised text-ink'
                                : 'text-muted hover:bg-surface-raised/60 hover:text-ink',
                        ]"
                    >
                        {{ link.label }}
                    </Link>
                    <span
                        class="rounded-lg px-3 py-2 text-sm text-muted/50"
                        title="Coming soon"
                    >
                        Billing
                    </span>
                </nav>

                <div class="ml-auto flex items-center gap-4 text-sm">
                    <span class="hidden text-muted md:inline">{{ user?.name }}</span>
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
                        <span class="hidden sm:inline">Log out</span>
                    </button>
                </div>
            </div>

            <nav
                class="flex gap-1 overflow-x-auto border-t border-border px-4 py-2 sm:hidden"
                aria-label="Primary mobile"
            >
                <Link
                    v-for="link in navLinks"
                    :key="`mobile-${link.href}`"
                    :href="link.href"
                    :class="[
                        'whitespace-nowrap rounded-lg px-3 py-2 text-sm transition',
                        isActive(link.match)
                            ? 'bg-surface-raised text-ink'
                            : 'text-muted hover:text-ink',
                    ]"
                >
                    {{ link.label }}
                </Link>
                <span class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-muted/50">
                    Billing
                </span>
            </nav>
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
