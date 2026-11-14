/*
 * Service worker of the offline collection app.
 * The app shell is served from the cache first so the app opens without internet;
 * every request also refreshes the cache in the background. The API is never cached.
 * Background Sync: when records were saved offline, the browser wakes this worker as soon as
 * the internet is back (even with the app closed) and it pushes them to the central server.
 * Bump CACHE_VERSION (and App\Support\MobileApp::VERSION) after changing the app files.
 */
const CACHE_VERSION = 'toll-mobile-1.1.4';
const ASSET_VERSION = '1.1.4';

importScripts('/mobile/sync-core.js?v=' + ASSET_VERSION);

const SHELL = [
    '/mobile/app',
    '/mobile/app.css?v=' + ASSET_VERSION,
    '/mobile/sync-core.js?v=' + ASSET_VERSION,
    '/mobile/app.js?v=' + ASSET_VERSION,
    '/mobile/manifest.webmanifest',
    '/mobile/icons/icon-192.png',
    '/mobile/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== self.location.origin || !url.pathname.startsWith('/mobile/')) {
        return;
    }

    event.respondWith(caches.open(CACHE_VERSION).then(async (cache) => {
        const cached = await cache.match(event.request);
        const network = fetch(event.request)
            .then((response) => {
                if (response.ok) {
                    cache.put(event.request, response.clone());
                }

                return response;
            })
            .catch(() => cached);

        return cached || network;
    }));
});

/** Pushes the records saved offline; a network failure makes the browser retry later on its own. */
const pushInBackground = async () => {
    const auth = await TollSync.kvGet('auth');

    if (!auth?.token) {
        return;
    }

    const { ran, value: sent } = await TollSync.exclusive(() => TollSync.pushPending(auth.token)).catch((error) => {
        if (error instanceof TollSync.ApiError && error.isTransient) {
            throw error;
        }

        return { ran: false };
    });

    if (ran && sent > 0) {
        const clients = await self.clients.matchAll({ type: 'window' });
        clients.forEach((client) => client.postMessage({ type: 'pushed', count: sent }));
    }
};

self.addEventListener('sync', (event) => {
    if (event.tag === TollSync.BACKGROUND_SYNC_TAG) {
        event.waitUntil(pushInBackground());
    }
});
