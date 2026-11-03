import { router } from '@inertiajs/react';

/**
 * App-wide motion with no per-page code: cards (.shadow-card) and .reveal elements rise in as they scroll into
 * view, with a small stagger for those that arrive together, and each new page fades in. Respects
 * prefers-reduced-motion; elements inside dialogs are left alone.
 */
const SELECTOR = '.reveal, .shadow-card';
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

const seen = new WeakSet<Element>();
let batch = 0;
let batchTimer: number | undefined;

const observer = new IntersectionObserver(
    (entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            const el = entry.target as HTMLElement;
            observer.unobserve(el);
            el.style.setProperty('--motion-delay', `${Math.min(batch++, 6) * 60}ms`);
            el.classList.add('motion-in');
            el.classList.remove('motion-pending');
            el.addEventListener('transitionend', () => el.classList.remove('motion-in'), { once: true });
        }
        window.clearTimeout(batchTimer);
        batchTimer = window.setTimeout(() => (batch = 0), 120);
    },
    { rootMargin: '0px 0px -6% 0px', threshold: 0.05 },
);

function watch(root: ParentNode): void {
    root.querySelectorAll(SELECTOR).forEach((el) => {
        if (seen.has(el) || el.closest('[role="dialog"], dialog, [data-no-motion]')) return;
        seen.add(el);
        el.classList.add('motion-pending');
        observer.observe(el);
    });
}

export function installMotion(): void {
    if (reduced.matches || !('IntersectionObserver' in window)) return;
    const app = document.getElementById('app');
    if (!app) return;
    watch(app);
    new MutationObserver((mutations) => {
        for (const m of mutations) m.addedNodes.forEach((n) => n instanceof HTMLElement && watch(n.parentNode ?? n));
    }).observe(app, { childList: true, subtree: true });

    let component: string | null = null;
    router.on('navigate', (event) => {
        const next = event.detail.page.component;
        if (component !== null && next !== component) {
            document.querySelector('main')?.animate(
                [
                    { opacity: 0, transform: 'translateY(8px)' },
                    { opacity: 1, transform: 'none' },
                ],
                { duration: 280, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' },
            );
        }
        component = next;
    });
}
