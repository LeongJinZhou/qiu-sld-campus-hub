import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/** Visible filters drive the URL: after the person stops typing, the page reloads with the non-empty values. */
export function cleanParams(filters: Record<string, unknown>): Record<string, string> {
    return Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined).map(([k, v]) => [k, String(v)]));
}

export function useFilterVisit(url: string, filters: Record<string, unknown>, delay = 350): Record<string, string> {
    const params = cleanParams(filters);
    const json = JSON.stringify(params);
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => router.get(url, JSON.parse(json) as Record<string, string>, { preserveState: true, preserveScroll: true, replace: true }), delay);
        return () => clearTimeout(timer);
    }, [json, url, delay]);
    return params;
}
