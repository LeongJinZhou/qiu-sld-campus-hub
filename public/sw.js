// Campus Hub service worker: an offline shell only. Pages and data always come from the network, so no
// personal data is ever cached; built assets (hashed file names) and the brand icons are cached for speed.
// Every path is taken from the worker's own scope, so it works at a domain root and in a subfolder (/campus-hub/).
const BASE = new URL(self.registration.scope).pathname; // '/' or '/campus-hub/'
const CACHE = `campus-hub-v3:${BASE}`;
const OFFLINE = `${BASE}offline.html`;
const SHELL = [OFFLINE, `${BASE}icons/icon-192.png`, `${BASE}manifest.webmanifest`];
const CACHED = [`${BASE}build/`, `${BASE}icons/`, `${BASE}brand/`];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('campus-hub-') && k.endsWith(`:${BASE}`) && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => caches.keys())
            // Caches from before the subfolder-aware worker (names without a scope) are always stale.
            .then((keys) => Promise.all(keys.filter((k) => k === 'campus-hub-v2' || k.startsWith('qiu-campus-hub-')).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) return;

    // Full page loads: network, falling back to the offline page.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
        return;
    }
    // Versioned build assets and icons: cache first.
    const path = new URL(request.url).pathname;
    if (CACHED.some((prefix) => path.startsWith(prefix))) {
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
    const url = event.notification.data?.url ?? `${BASE}notifications`;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((w) => new URL(w.url).origin === self.location.origin);
            return open ? open.navigate(url).then((w) => w?.focus()) : self.clients.openWindow(url);
        }),
    );
});
