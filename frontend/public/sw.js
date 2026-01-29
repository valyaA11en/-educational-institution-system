const CACHE_NAME = 'pdo-v1';
const API_CACHE_NAME = 'pdo-api-v1';
const urlsToCache = [
  '/',
  '/index.html',
  '/manifest.json',
];

// API endpoints to cache for offline read-only access
const API_ENDPOINTS_TO_CACHE = [
  '/api/v1/schedule',
  '/api/v1/notifications',
];

// Install event - cache resources
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        // Open API cache
        return caches.open(API_CACHE_NAME);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  
  // Check if this is an API endpoint we want to cache
  const isCacheableApiEndpoint = API_ENDPOINTS_TO_CACHE.some(endpoint => 
    url.pathname.startsWith(endpoint)
  );

  if (isCacheableApiEndpoint && event.request.method === 'GET') {
    // For cacheable API endpoints: network first, fallback to cache
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // Clone the response because it can only be consumed once
          const responseToCache = response.clone();
          
          // Cache successful responses
          if (response.status === 200) {
            caches.open(API_CACHE_NAME).then((cache) => {
              cache.put(event.request, responseToCache);
            });
          }
          
          return response;
        })
        .catch(() => {
          // Network failed, try cache
          return caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // If no cache, return offline response
            return new Response(
              JSON.stringify({ 
                offline: true, 
                message: 'Нет подключения к интернету. Показаны кэшированные данные.' 
              }),
              {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
              }
            );
          });
        })
    );
  } else {
    // For other requests: cache first, fallback to network
    event.respondWith(
      caches.match(event.request)
        .then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          return fetch(event.request).then((response) => {
            // Don't cache non-GET requests or non-successful responses
            if (event.request.method !== 'GET' || !response.ok) {
              return response;
            }
            
            // Clone the response
            const responseToCache = response.clone();
            
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseToCache);
            });
            
            return response;
          });
        })
    );
  }
});

// Push event - handle push notifications
self.addEventListener('push', (event) => {
  let data = {};
  
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { title: 'PDO', body: event.data.text() || 'Новое уведомление' };
    }
  }

  const options = {
    body: data.body || 'Новое уведомление',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/icon-96x96.png',
    vibrate: [200, 100, 200],
    data: data.data || {},
    tag: data.tag || 'default',
    requireInteraction: data.requireInteraction || false,
    actions: data.actions || [],
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'PDO', options)
  );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const urlToOpen = event.notification.data?.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Check if there's already a window/tab open with the target URL
        for (let i = 0; i < clientList.length; i++) {
          const client = clientList[i];
          if (client.url === urlToOpen && 'focus' in client) {
            return client.focus();
          }
        }
        // If not, open a new window/tab
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

// Background sync (optional - for offline actions)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-notifications') {
    event.waitUntil(
      // Sync notifications when back online
      fetch('/api/v1/notifications')
        .then((response) => response.json())
        .catch((err) => console.error('Sync failed:', err))
    );
  }
});








