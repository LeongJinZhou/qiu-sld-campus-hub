import '../css/app.css';

import { installMotion } from '@react/lib/motion';
import { createInertiaApp, router } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} · QIU SLD Campus Hub` : 'QIU SLD Campus Hub'),
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return (await page()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
        installMotion();
    },
    progress: { color: '#E01CEB' },
});

// The theme belongs to whoever is signed in: after every visit the page takes that person's own choice.
router.on('navigate', (event) => {
    const theme = (event.detail.page.props as { auth?: { user?: { theme?: string } | null } }).auth?.user?.theme;
    document.documentElement.dataset.theme = theme === 'light' || theme === 'dark' ? theme : 'system';
});

// Installable app: offline shell only (public/sw.js). Service workers need HTTPS, or localhost in development.
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => undefined));
}
