import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export type AutosaveState = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Saves a draft form a moment after the person stops typing, through the form's own "Save draft" endpoint.
 * A partial reload of nothing keeps the page (and its flash toast) quiet; the returned state drives a small
 * "Saved" note. Nothing is sent while the form is read-only or already being submitted.
 */
export function useAutosave(url: string, body: object, enabled: boolean, busy = false): AutosaveState {
    const [state, setState] = useState<AutosaveState>('idle');
    const json = JSON.stringify(body);
    const first = useRef(true);
    const waitingSince = useRef<number | null>(null);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        if (!enabled || busy) return;
        waitingSince.current ??= Date.now();
        // 2 s after typing stops, but never more than 15 s after the first unsaved change.
        const delay = Math.max(0, Math.min(2000, 15000 - (Date.now() - waitingSince.current)));
        const timer = setTimeout(() => {
            waitingSince.current = null;
            setState('saving');
            router.put(url, JSON.parse(json) as Record<string, never>, {
                preserveScroll: true,
                preserveState: true,
                only: ['autosaved'],
                onSuccess: () => setState('saved'),
                onError: () => setState('error'),
            });
        }, delay);
        return () => clearTimeout(timer);
    }, [json, enabled, busy, url]);

    return state;
}

/**
 * For a form with no server-side draft yet (a new application or request): keeps an unfinished draft on this
 * device, per signed-in person, restores it on the first render and forgets it once the form is sent.
 * Storage can be unavailable (private mode, blocked site data); the form then simply works without a draft.
 */
export function useLocalDraft<T extends object>(key: string, data: T, restore: (saved: Partial<T>) => void): { restored: boolean; clear: () => void } {
    const userId = usePage().props.auth.user?.id ?? 'guest';
    const storageKey = `draft:${userId}:${key}`;
    const [restored, setRestored] = useState(false);
    const loaded = useRef(false);
    const json = JSON.stringify(data);

    useEffect(() => {
        if (loaded.current) return;
        loaded.current = true;
        try {
            const raw = localStorage.getItem(storageKey);
            if (raw) {
                restore(JSON.parse(raw) as Partial<T>);
                setRestored(true);
            }
        } catch {
            // No storage, or a corrupt draft: start fresh.
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [storageKey]);

    useEffect(() => {
        if (!loaded.current) return;
        const timer = setTimeout(() => {
            try {
                localStorage.setItem(storageKey, json);
            } catch {
                // Storage full or blocked: nothing to keep.
            }
        }, 800);
        return () => clearTimeout(timer);
    }, [json, storageKey]);

    return {
        restored,
        clear: () => {
            try {
                localStorage.removeItem(storageKey);
            } catch {
                // Nothing to clear.
            }
        },
    };
}
