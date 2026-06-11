/* Solar — service worker
 * ----------------------------------------------------------------------------
 * Versioned cache name: bump the suffix (v1, v2, v3, …) on EVERY release
 * that ships changes to assets the SW caches. The activate handler will
 * sweep any cache whose name is not in CACHE_ALLOWLIST, so old versions
 * are cleaned up automatically and users always get the new shell.
 *
 * Caching strategy:
 *   - Pre-cache (install):   /, /dashboard, /manifest.json, /pwa/icon-192.png.
 *   - Static (style, script, image, font):
 *                            cache-first, fall back to network, cache the
 *                            network response on success.
 *   - HTML navigations:      network-first, fall back to cached "/", cache
 *                            the new page so offline navigation works.
 *   - API (/api/*, /sanctum/*):  network-only — never serve stale financial
 *                            data. A failed request propagates to the page,
 *                            which renders the proper error UI.
 *
 * Update flow:
 *   1. Browser detects new SW in the background (updatefound).
 *   2. sw-register.js listens, shows a "Nova versão disponível" toast.
 *   3. User clicks "Atualizar" → register posts {type:'SKIP_WAITING'}.
 *   4. The new SW calls skipWaiting(), claims clients, activates.
 *   5. Reload triggers a fresh navigation → user sees new shell.
 * ----------------------------------------------------------------------------
 */

/* eslint-disable no-restricted-globals */
const CACHE_VERSION = 'v1';
const CACHE_NAME = `solar-${CACHE_VERSION}`;
const CACHE_ALLOWLIST = [CACHE_NAME];

/* Pre-cache list — the app shell. */
const PRECACHE_URLS = [
    '/',
    '/dashboard',
    '/manifest.json',
    '/pwa/icon-192.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            /* Best-effort precache. We add each URL individually so a single
             * 404 does not abort the whole install. */
            return Promise.allSettled(
                PRECACHE_URLS.map((url) =>
                    fetch(url, { credentials: 'same-origin' })
                        .then((res) => (res.ok ? cache.put(url, res) : null))
                        .catch(() => null),
                ),
            );
        }).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => !CACHE_ALLOWLIST.includes(key))
                    .map((key) => caches.delete(key)),
            );
        }).then(() => self.clients.claim()),
    );
});

/* Allow the page to trigger skipWaiting after the user accepts an update. */
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

/* Helper: is this a same-origin request we own? */
function isSameOrigin(request) {
    try {
        const url = new URL(request.url);
        return url.origin === self.location.origin;
    } catch (_) {
        return false;
    }
}

/* Helper: is this a request we should NEVER cache (sensitive / live)? */
function isAlwaysNetworkOnly(request) {
    const url = new URL(request.url);
    if (url.pathname.startsWith('/api/')) return true;
    if (url.pathname.startsWith('/sanctum/')) return true;
    if (url.pathname.startsWith('/broadcasting/')) return true;
    if (url.pathname.startsWith('/livewire/')) return true;
    /* Anything that is not GET is non-cacheable by spec. */
    if (request.method !== 'GET') return true;
    return false;
}

/* Helper: static asset destination — cache-first. */
function isStaticAsset(request) {
    const dest = request.destination;
    return (
        dest === 'style' ||
        dest === 'script' ||
        dest === 'image' ||
        dest === 'font' ||
        dest === 'manifest'
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    /* Only handle same-origin GETs. Let the browser handle cross-origin
     * (e.g. fonts.bunny.net) directly. */
    if (!isSameOrigin(request) || request.method !== 'GET') return;

    /* Network-only for sensitive / live endpoints. */
    if (isAlwaysNetworkOnly(request)) {
        event.respondWith(
            fetch(request).catch(() => {
                /* For API failures we let the browser surface the network
                 * error. We do NOT return a cached response — that would
                 * be a financial-data correctness bug. */
                return new Response(
                    JSON.stringify({ error: 'offline', message: 'Sem conexão' }),
                    { status: 503, headers: { 'Content-Type': 'application/json' } },
                );
            }),
        );
        return;
    }

    /* HTML navigation: network-first with cached "/" fallback. */
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    /* Stash a copy of successful navigations for offline use. */
                    if (response && response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(request).then(
                        (cached) => cached || caches.match('/'),
                    ),
                ),
        );
        return;
    }

    /* Static assets: cache-first, fall back to network, cache the response. */
    if (isStaticAsset(request)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request)
                    .then((response) => {
                        if (response && response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    })
                    .catch(() => cached || Response.error());
            }),
        );
        return;
    }

    /* Everything else: pass through to the network. */
});
