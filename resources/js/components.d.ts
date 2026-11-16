import type { OhVueIcon } from 'oh-vue-icons';

declare module 'vue' {
    interface GlobalComponents {
        VIcon: typeof OhVueIcon;
    }
}

export {};
