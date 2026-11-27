import { appPath, url } from '@/lib/url';
import { useEffect, useRef } from 'react';

/**
 * While a payment or form is half-way, leaving the app (home button, switching apps, locking the phone) leaves a
 * reminder behind: an in-app notification, plus a phone notification if the person allowed them. Tapping it
 * brings them straight back to this page.
 */
export function useLeaveReminder(title: string, active: boolean): void {
    const latest = useRef({ title, active });
    latest.current = { title, active };

    useEffect(() => {
        const onHide = (): void => {
            const { title: name, active: on } = latest.current;
            if (document.visibilityState !== 'hidden' || !on) return;
            // The server builds the link with url(), which adds the folder itself, so it gets the app path;
            // the phone notification opens the full path.
            const path = location.pathname + location.search;
            const appPagePath = appPath(location.pathname) + location.search;
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
            void fetch(url('/notifications/left-halfway'), {
                method: 'POST',
                keepalive: true, // still sent while the page is being hidden
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ title: name, path: appPagePath }),
            }).catch(() => undefined);
            if ('Notification' in window && Notification.permission === 'granted' && 'serviceWorker' in navigator) {
                void navigator.serviceWorker.ready.then((sw) =>
                    sw.showNotification('It seems you have half-way done this', { body: `${name} is not finished. Tap to continue.`, tag: path, data: { url: path }, icon: url('/icons/icon-192.png') }),
                );
            }
        };
        document.addEventListener('visibilitychange', onHide);
        return () => document.removeEventListener('visibilitychange', onHide);
    }, []);
}
