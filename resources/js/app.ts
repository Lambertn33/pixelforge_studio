import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { addIcons, OhVueIcon } from 'oh-vue-icons';
import {
    HiChevronLeft,
    HiExclamationCircle,
    HiLockClosed,
    HiLogout,
    HiMail,
    HiPhotograph,
    HiPlus,
    HiUser,
} from 'oh-vue-icons/icons';
import type { App } from 'vue';

addIcons(
    HiChevronLeft,
    HiExclamationCircle,
    HiLockClosed,
    HiLogout,
    HiMail,
    HiPhotograph,
    HiPlus,
    HiUser,
);

createInertiaApp({
    pages: './Pages',
    title: (title) =>
        title ? `${title} — PixelForge Studio` : 'PixelForge Studio',
    progress: {
        color: '#9ae600',
    },
    withApp(app: App) {
        app.component('VIcon', OhVueIcon);
    },
});
