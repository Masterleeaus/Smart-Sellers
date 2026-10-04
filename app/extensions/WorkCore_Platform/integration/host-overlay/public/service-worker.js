// Service Worker for Meetup Chat PWA
const CACHE_VERSION = 'meetup-chat-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const MAX_DYNAMIC_CACHE_SIZE = 50;

// Assets to cache on install
const STATIC_ASSETS = [
    '/',
    '/css/chat.css',
    '/css/login.css',
    '/css/responsive.css',
    '/js/lucide.min.js',
    '/js/pwa.js',
    '/offline.html',
    '/icon-192.png',
    '/icon-512.png',
    '/apple-touch-icon.png',
    '/favicon-32x32.png',
    '/favicon-16x16.png',
    '/favicon.ico'
];

// Limit cache size
const limitCacheSize = (cacheName, maxSize) => {
    caches.open(cacheName)
        .then(cache => {
            return cache.keys()
                .then(keys => {
                    if (keys.length > maxSize) {
                        return cache.delete(keys[0])
                            .then(() => limitCacheSize(cacheName, maxSize))
                            .catch(err => console.error('[Service Worker] Failed to delete cache entry:', err));
                    }
                })
                .catch(err => console.error('[Service Worker] Failed to retrieve cache keys:', err));
        })
        .catch(err => console.error('[Service Worker] Failed to open cache:', err));
};

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('[Service Worker] Installing...');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[Service Worker] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
            .catch(err => console.error('[Service Worker] Install failed:', err))
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('[Service Worker] Activating...');
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name.startsWith('meetup-chat-') && name !== STATIC_CACHE && name !== DYNAMIC_CACHE)
                        .map(name => {
                            console.log('[Service Worker] Deleting old cache:', name);
                            return caches.delete(name)
                                .catch(err => console.error('[Service Worker] Failed to delete cache:', name, err));
                        })
                );
            })
            .then(() => self.clients.claim())
            .catch(err => console.error('[Service Worker] Activate failed:', err))
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip non-HTTP(S) requests (chrome-extension, etc.)
    if (!url.protocol.startsWith('http')) {
        return;
    }

    // Skip API calls and broadcasting (use network-first for dynamic content)
    if (url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/broadcasting/') ||
        url.pathname.includes('pusher')) {
        event.respondWith(
            fetch(request)
                .catch(() => {
                    return new Response(
                        JSON.stringify({ error: 'Network unavailable' }),
                        {
                            status: 503,
                            headers: { 'Content-Type': 'application/json' }
                        }
                    );
                })
        );
        return;
    }

    // Cache-first strategy for static assets
    event.respondWith(
        caches.match(request)
            .then(cachedResponse => {
                if (cachedResponse) {
                    return cachedResponse;
                }

                // Not in cache, fetch from network
                return fetch(request)
                    .then(networkResponse => {
                        // Don't cache non-successful responses
                        if (!networkResponse || networkResponse.status !== 200) {
                            return networkResponse;
                        }

                        // Clone the response
                        const responseToCache = networkResponse.clone();

                        // Cache dynamic content
                        caches.open(DYNAMIC_CACHE)
                            .then(cache => {
                                return cache.put(request, responseToCache)
                                    .then(() => limitCacheSize(DYNAMIC_CACHE, MAX_DYNAMIC_CACHE_SIZE))
                                    .catch(err => console.error('[Service Worker] Failed to cache response:', err));
                            })
                            .catch(err => console.error('[Service Worker] Failed to open dynamic cache:', err));

                        return networkResponse;
                    })
                    .catch(err => {
                        console.error('[Service Worker] Fetch failed:', request.url, err);
                        // Network failed, try to serve offline page for navigation requests
                        if (request.destination === 'document') {
                            return caches.match('/offline.html')
                                .catch(offlineErr => {
                                    console.error('[Service Worker] Failed to load offline page:', offlineErr);
                                    return new Response('Offline - unable to load page', { status: 503 });
                                });
                        }
                    });
            })
            .catch(err => {
                console.error('[Service Worker] Cache match failed:', request.url, err);
                // Fallback to network fetch
                return fetch(request)
                    .catch(() => {
                        return new Response('Service unavailable', { status: 503 });
                    });
            })
    );
});

// Handle messages from clients
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('sync', event => {
    if (event.tag === 'titan-zero-sync') {
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then(clients => {
                    try {
                        clients.forEach(client => {
                            try {
                                client.postMessage({ type: 'TITAN_ZERO_FLUSH_OPERATIONS' });
                            } catch (err) {
                                console.error('[Service Worker] Failed to post message to client:', err);
                            }
                        });
                    } catch (err) {
                        console.error('[Service Worker] Error processing clients:', err);
                    }
                })
                .catch(err => console.error('[Service Worker] Failed to match clients:', err))
        );
    }
});
