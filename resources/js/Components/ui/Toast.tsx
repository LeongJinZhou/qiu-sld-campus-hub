import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * The message from the action this person just took (flash data lives in their own session, so nobody else sees it).
 * Shown once per real page visit: the background badge refresh (a partial reload every 20 s) must never replay it.
 */
export default function Toast() {
    const page = usePage();
    const flashRef = useRef(page.props.flash);
    flashRef.current = page.props.flash;
    const [message, setMessage] = useState<{ text: string; tone: 'success' | 'error' } | null>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    const show = useCallback((): void => {
        const flash = flashRef.current;
        const text = flash.error ?? flash.success;
        if (!text) return;
        setMessage({ text, tone: flash.error ? 'error' : 'success' });
        clearTimeout(timer.current);
        timer.current = setTimeout(() => setMessage(null), 4000);
    }, []);

    useEffect(() => {
        show();
        const off = router.on('finish', (e) => {
            const visit = e.detail.visit;
            // The page props update after 'finish'; read them on the next tick.
            if (visit.completed && !visit.only.length && !visit.except.length) setTimeout(show, 0);
        });
        // A refused upload or form comes back as validation errors; pages that do not print them would stay silent.
        const offError = router.on('error', (e) => {
            const first = Object.values(e.detail.errors)[0];
            if (!first) return;
            setMessage({ text: String(first), tone: 'error' });
            clearTimeout(timer.current);
            timer.current = setTimeout(() => setMessage(null), 6000);
        });
        return () => {
            off();
            offError();
            clearTimeout(timer.current);
        };
    }, [show]);

    const dismiss = (): void => {
        clearTimeout(timer.current);
        setMessage(null);
    };

    return (
        <div className="pointer-events-none fixed inset-x-0 top-4 z-50 flex justify-center px-4" aria-live="polite">
            {message && (
                <button
                    type="button"
                    className={`fade-up pointer-events-auto rounded-control px-4 py-3 text-left text-sm font-medium text-white shadow-pop ${message.tone === 'error' ? 'bg-danger' : 'bg-ink'}`}
                    role="status"
                    title="Dismiss"
                    onClick={dismiss}
                >
                    {message.text}
                </button>
            )}
        </div>
    );
}
