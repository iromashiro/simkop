// HERMES Service Worker - Offline Support & Caching

const CACHE_NAME = 'hermes-v1.0.0';
const STATIC_CACHE = 'hermes-static-v1.0.0';
const DYNAMIC_CACHE = 'hermes-dynamic-v1.0.0';

// Files to cache immediately
const STATIC_FILES = [
    '/',
    '/css/app.css',
    '/js/app.js',
    '/js/hermes-advanced.js',
    '/favicon.ico',
    '/manifest.json',
    // Bootstrap & Alpine.js CDN fallbacks
    '/offline.html'
];

// API endpoints to cache
const CACHE_API_PATTERNS = [
    /\/api\/dashboard\/stats/,
    /\/api\/members\/\d+/,
    /\/api\/savings\/\d+/,
    /\/api\/loans\/\d+/
];

// Install event - cache static files
self.addEventListener('install', event => {
    console.log('Service Worker installing...');

    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Caching static files...');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => {
                return self.skipWaiting();
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');

    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Handle different types of requests
    if (isStaticFile(request)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
    } else if (isAPIRequest(request)) {
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
    } else if (isPageRequest(request)) {
        event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE));
    } else {
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
    }
});

// Background sync for offline actions
self.addEventListener('sync', event => {
    console.log('Background sync triggered:', event.tag);

    if (event.tag === 'background-sync') {
        event.waitUntil(processOfflineActions());
    }
});

// Push notifications
self.addEventListener('push', event => {
    console.log('Push notification received:', event);

    const options = {
        body: event.data ? event.data.text() : 'New notification from HERMES',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View Details',
                icon: '/icons/checkmark.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/icons/xmark.png'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('HERMES Koperasi', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    console.log('Notification clicked:', event);

    event.notification.close();

    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/dashboard')
        );
    }
});

// Helper functions
function isStaticFile(request) {
    return request.url.includes('/css/') ||
           request.url.includes('/js/') ||
           request.url.includes('/images/') ||
           request.url.includes('/fonts/') ||
           request.url.endsWith('.ico');
}

function isAPIRequest(request) {
    return request.url.includes('/api/');
}

function isPageRequest(request) {
    return request.headers.get('accept').includes('text/html');
}

// Caching strategies
async function cacheFirst(request, cacheName) {
    try {
        const cache = await caches.open(cacheName);
        const cachedResponse = await cache.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        const networkResponse = await fetch(request);

        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        console.error('Cache first strategy failed:', error);
        return new Response('Offline', { status: 503 });
    }
}

async function networkFirst(request, cacheName) {
    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        console.log('Network failed, trying cache:', error);

        const cache = await caches.open(cacheName);
        const cachedResponse = await cache.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        // Return offline page for navigation requests
        if (isPageRequest(request)) {
            return caches.match('/offline.html');
        }

        return new Response('Offline', { status: 503 });
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    });

    return cachedResponse || fetchPromise;
}

async function processOfflineActions() {
    // Process any queued offline actions
    const offlineActions = await getOfflineActions();

    for (const action of offlineActions) {
        try {
            await fetch(action.url, {
                method: action.method,
                headers: action.headers,
                body: action.body
            });

            // Remove successful action from queue
            await removeOfflineAction(action.id);
        } catch (error) {
            console.error('Failed to process offline action:', error);
        }
    }
}

async function getOfflineActions() {
    // Implementation would depend on IndexedDB or similar storage
    return [];
}

async function removeOfflineAction(id) {
    // Implementation would depend on IndexedDB or similar storage
}
