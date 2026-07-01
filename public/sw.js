const CACHE_NAME = 'chaodu-shell-v2';
const ASSETS = ['/', '/manifest.webmanifest', '/favicon.ico', '/apple-touch-icon.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ),
        ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('/')),
        );

        return;
    }

    if (
        url.pathname.startsWith('/storage') ||
        url.pathname.startsWith('/admin') ||
        url.pathname.startsWith('/api')
    ) {
        return;
    }

    const shouldCacheAsset =
        url.pathname.startsWith('/build/') ||
        /\.(?:js|css|woff2?|png|jpg|jpeg|svg|ico|webmanifest)$/i.test(url.pathname);

    if (!shouldCacheAsset) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(event.request).then((networkResponse) => {
                if (networkResponse.status === 200 && networkResponse.type === 'basic') {
                    const copy = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, copy);
                    });
                }

                return networkResponse;
            });
        }),
    );
});
