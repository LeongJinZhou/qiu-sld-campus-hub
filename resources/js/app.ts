import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} · QIU SLD Campus Hub` : 'QIU SLD Campus Hub'),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];
        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }
        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: '#E01CEB' },
});

// Installable app: offline shell only (public/sw.js). Service workers need HTTPS, or localhost in development.
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => undefined));
}
