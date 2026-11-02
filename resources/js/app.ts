import '../css/app.css';

import { installMotion } from './lib/motion';
import { createInertiaApp, router } from '@inertiajs/vue3';
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
        installMotion();
    },
    progress: { color: '#E01CEB' },
});

// The theme belongs to whoever is signed in: after every visit (sign-in, sign-out, switching accounts without a
// full reload) the page takes that person's own choice, or "system" when nobody is signed in.
router.on('navigate', (event) => {
    const theme = (event.detail.page.props as { auth?: { user?: { theme?: string } | null } }).auth?.user?.theme;
    document.documentElement.dataset.theme = theme === 'light' || theme === 'dark' ? theme : 'system';
});

// Installable app: offline shell only (public/sw.js). Service workers need HTTPS, or localhost in development.
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => undefined));
}
