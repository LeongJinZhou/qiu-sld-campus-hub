import { onBeforeUnmount, onMounted, type MaybeRefOrGetter, toValue } from 'vue';

/**
 * While a payment or form is half-way, leaving the app (home button, switching apps, locking the phone) leaves a
 * reminder behind: an in-app notification, plus a phone notification if the person allowed them. Tapping it
 * brings them straight back to this page.
 */
export function useLeaveReminder(title: MaybeRefOrGetter<string>, active: MaybeRefOrGetter<boolean>): void {
    const onHide = (): void => {
        if (document.visibilityState !== 'hidden' || !toValue(active)) return;
        const path = location.pathname + location.search;
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
        void fetch('/notifications/left-halfway', {
            method: 'POST',
            keepalive: true, // still sent while the page is being hidden
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ title: toValue(title), path }),
        }).catch(() => undefined);
        if ('Notification' in window && Notification.permission === 'granted' && 'serviceWorker' in navigator) {
            void navigator.serviceWorker.ready.then((sw) =>
                sw.showNotification('It seems you have half-way done this', { body: `${toValue(title)} is not finished. Tap to continue.`, tag: path, data: { url: path }, icon: '/icons/icon-192.png' }),
            );
        }
    };
    onMounted(() => document.addEventListener('visibilitychange', onHide));
    onBeforeUnmount(() => document.removeEventListener('visibilitychange', onHide));
}
