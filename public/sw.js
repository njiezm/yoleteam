// YoleTeam service worker: keeps the appel and crew plan pages (and the built assets) available offline.
// Pages: network first, cached copy when the network is down or too slow. Assets: cache first.
// Writes (PUT/POST) are never intercepted: offline changes go through the IndexedDB queue (resources/js/offline-queue.js).

const PAGES = 'yt-pages-v1';
const ASSETS = 'yt-assets-v1';
const OFFLINE_PAGE = '/offline.html';
const STATIC = [OFFLINE_PAGE, '/manifest.webmanifest', '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png'];
const NETWORK_TIMEOUT = 5000;

// Only the screens used on the water are kept offline.
const OFFLINE_ROUTES = [
    /^\/$/,
    /^\/sorties\/\d+$/,
    /^\/sorties\/\d+\/appel$/,
    /^\/sorties\/\d+\/equipages\/\d+(\/modifier)?$/,
    /^\/synchronisation$/,
];
// Menu shortcuts that redirect to the current outing ("Appel", "Équipage"): kept with the page they lead to.
const SHORTCUTS = ['/appel', '/equipage'];
const isOfflineRoute = (path) => OFFLINE_ROUTES.some((re) => re.test(path));
const isShortcut = (path) => SHORTCUTS.includes(path);
const pageKey = (url) => new URL(url, self.location.origin).origin + new URL(url, self.location.origin).pathname;

self.addEventListener('install', (event) => {
    // One entry at a time and never fatal: a failed or leftover entry must not block the worker install.
    event.waitUntil(
        caches.open(ASSETS)
            .then((cache) => Promise.all(STATIC.map((url) => fetch(url)
                .then((response) => (response.ok ? cache.put(url, response) : null))
                .catch(() => null))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => ![PAGES, ASSETS].includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// A redirected response cannot answer a navigation: keep a plain copy of the final page under the shortcut.
async function storeShortcut(url, response) {
    const target = new URL(response.url).pathname;
    if (!response.ok || response.type !== 'basic' || !isOfflineRoute(target)) return response;
    const copy = response.clone();
    try {
        const body = await copy.blob();
        const plain = () => new Response(body, { status: copy.status, statusText: copy.statusText, headers: copy.headers });
        const cache = await caches.open(PAGES);
        await cache.put(pageKey(url), plain());
        await cache.put(pageKey(target), plain());
    } catch {
        // Storage full or unavailable: serve the page anyway.
    }
    return response;
}

async function storePage(url, response) {
    if (isShortcut(new URL(url, self.location.origin).pathname)) {
        if (response.redirected) return storeShortcut(url, response);
        // Navigations get the redirect unfollowed ("opaqueredirect"): fetch the final page separately for the cache.
        if (response.type === 'opaqueredirect') {
            fetch(url, { credentials: 'same-origin' }).then((followed) => storeShortcut(url, followed)).catch(() => null);
        }
        return response;
    }
    if (response.ok && !response.redirected && response.type === 'basic') {
        // Storage can be full or unavailable: a failed cache write must never break the page that is being served.
        await caches.open(PAGES)
            .then((cache) => cache.put(pageKey(url), response.clone()))
            .catch(() => null);
    }
    return response;
}

async function navigate(request) {
    const path = new URL(request.url).pathname;
    const network = fetch(request).then((response) => (isOfflineRoute(path) || isShortcut(path) ? storePage(request.url, response) : response));

    try {
        return await Promise.race([
            network,
            new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), NETWORK_TIMEOUT)),
        ]);
    } catch {
        const cached = await caches.match(pageKey(request.url), { cacheName: PAGES });
        if (cached) return cached;
        try {
            return await network;
        } catch {
            return (await caches.match(OFFLINE_PAGE)) ?? new Response('Hors ligne', { status: 503 });
        }
    }
}

async function asset(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) {
        const copy = response.clone();
        caches.open(ASSETS).then((cache) => cache.put(request, copy)).catch(() => null);
    }
    return response;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(navigate(request));
    } else if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(asset(request));
    }
});

self.addEventListener('message', (event) => {
    const { type, urls = [] } = event.data || {};

    if (type === 'cache-assets') {
        event.waitUntil(caches.open(ASSETS).then((cache) => Promise.all(urls.map(async (url) => {
            if (!(await cache.match(url))) {
                const response = await fetch(url).catch(() => null);
                if (response?.ok) await cache.put(url, response);
            }
        }))));
    }

    if (type === 'cache-pages') {
        event.waitUntil(Promise.all(urls
            .filter((url) => { const path = new URL(url, self.location.origin).pathname; return isOfflineRoute(path) || isShortcut(path); })
            .map((url) => fetch(url, { credentials: 'same-origin' }).then((response) => storePage(url, response)).catch(() => null))));
    }
});
