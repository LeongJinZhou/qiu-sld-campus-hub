// Campus Hub service worker: an offline shell only. Pages and data always come from the network, so no
// personal data is ever cached; built assets (hashed file names) and the brand icons are cached for speed.
const CACHE = 'campus-hub-v2';
const SHELL = ['/offline.html', '/icons/icon-192.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) return;

    // Full page loads: network, falling back to the offline page.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }
    // Versioned build assets and icons: cache first.
    const path = new URL(request.url).pathname;
    if (path.startsWith('/build/') || path.startsWith('/icons/') || path.startsWith('/brand/')) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ??
                    fetch(request).then((response) => {
                        if (response.ok) caches.open(CACHE).then((cache) => cache.put(request, response.clone()));
                        return response;
                    }),
            ),
        );
    }
});

// Tapping a reminder ("you have half-way done this") opens the page where the person left off.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/notifications';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((w) => new URL(w.url).origin === self.location.origin);
            return open ? open.navigate(url).then((w) => w?.focus()) : self.clients.openWindow(url);
        }),
    );
});
