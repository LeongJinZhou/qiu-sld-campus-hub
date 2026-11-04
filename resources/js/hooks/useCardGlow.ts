import { useEffect } from 'react';

/**
 * The pointer-following glow on cards (as in the QIU Industry Day app): one delegated listener writes the pointer
 * position into the hovered card's --mouse-x / --mouse-y, and CSS draws the glow. Off for reduced motion.
 */
export function useCardGlow(): void {
    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        let frame = 0;
        const move = (e: PointerEvent): void => {
            if (frame) return;
            frame = requestAnimationFrame(() => {
                frame = 0;
                const card = (e.target as Element | null)?.closest?.<HTMLElement>('.rounded-card');
                if (!card) return;
                const box = card.getBoundingClientRect();
                card.style.setProperty('--mouse-x', `${e.clientX - box.left}px`);
                card.style.setProperty('--mouse-y', `${e.clientY - box.top}px`);
            });
        };
        document.addEventListener('pointermove', move, { passive: true });
        return () => document.removeEventListener('pointermove', move);
    }, []);
}
