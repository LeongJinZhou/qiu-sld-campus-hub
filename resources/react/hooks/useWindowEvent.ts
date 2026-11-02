import { useEffect, useRef } from 'react';

/** Listens on window for the component's lifetime, always calling the latest handler. */
export function useWindowEvent<K extends keyof WindowEventMap>(type: K, handler: (event: WindowEventMap[K]) => void, options?: AddEventListenerOptions): void {
    const latest = useRef(handler);
    latest.current = handler;
    useEffect(() => {
        const listener = (event: WindowEventMap[K]): void => latest.current(event);
        window.addEventListener(type, listener, options);
        return () => window.removeEventListener(type, listener, options);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [type]);
}
