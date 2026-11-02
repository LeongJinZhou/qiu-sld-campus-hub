import { router, usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { type MaybeRefOrGetter, ref, type Ref, toValue } from 'vue';

export type AutosaveState = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Saves a draft form a moment after the person stops typing, through the form's own "Save draft" endpoint.
 * A partial reload of nothing keeps the page (and its flash toast) quiet; the returned state drives a small
 * "Saved" note. Nothing is sent while the form is read-only or already being submitted.
 */
export function useAutosave(url: string, body: () => object, enabled: MaybeRefOrGetter<boolean>, busy: MaybeRefOrGetter<boolean> = false): Ref<AutosaveState> {
    const state = ref<AutosaveState>('idle');
    watchDebounced(
        () => JSON.stringify(body()),
        () => {
            if (!toValue(enabled) || toValue(busy)) return;
            state.value = 'saving';
            router.put(url, body() as Record<string, never>, {
                preserveScroll: true,
                preserveState: true,
                only: ['autosaved'],
                onSuccess: () => (state.value = 'saved'),
                onError: () => (state.value = 'error'),
            });
        },
        { debounce: 2000, maxWait: 15000 },
    );

    return state;
}

/**
 * For a form that has no server-side draft yet (a new application or request): keeps an unfinished draft on this
 * device, per signed-in person, restores it on the next visit and forgets it once the form is sent.
 * Storage can be unavailable (private mode, blocked site data); the form then simply works without a draft.
 */
export function useLocalDraft<T extends object>(key: string, data: () => T, restore: (saved: Partial<T>) => void): { restored: boolean; clear: () => void } {
    const storageKey = `draft:${usePage().props.auth.user?.id ?? 'guest'}:${key}`;
    let restored = false;
    try {
        const raw = localStorage.getItem(storageKey);
        if (raw) {
            restore(JSON.parse(raw) as Partial<T>);
            restored = true;
        }
    } catch {
        // No storage, or a corrupt draft: start fresh.
    }
    watchDebounced(
        () => JSON.stringify(data()),
        (json) => {
            try {
                localStorage.setItem(storageKey, json);
            } catch {
                // Storage full or blocked: nothing to keep.
            }
        },
        { debounce: 800 },
    );

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
