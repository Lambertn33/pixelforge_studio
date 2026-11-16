import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) =>
        title ? `${title} — PixelForge Studio` : 'PixelForge Studio',
    progress: {
        color: '#18181b',
    },
});
