const CACHE_VERSION = 'moto-levins-v11';
const NAVIGATION_NETWORK_TIMEOUT_MS = 15000;
const OFFLINE_URL = 'offline';

const CACHES = {
  static: `${CACHE_VERSION}-static`,
  html: `${CACHE_VERSION}-html`,
  images: `${CACHE_VERSION}-images`
};

const IMAGE_CACHE_LIMIT = 50;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHES.static).then((cache) => {
      // Setup minimum offline requirements. 
      // Relies on runtime caching for hashed assets instead of precaching everything blindly.
      return cache.addAll([OFFLINE_URL]);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (!Object.values(CACHES).includes(key)) {
            // Cleanup old caches for versioning safety
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Helper for basic cache limits
async function trimCache(cacheName, maxItems) {
  const cache = await caches.open(cacheName);
  let keys = await cache.keys();
  while (keys.length > maxItems) {
    await cache.delete(keys[0]);
    keys.shift();
  }
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // SECURITY: Only handle same-origin requests
  if (url.origin !== location.origin) {
    return;
  }

  // Skip admin panel - dynamic content, no offline need
  if (url.pathname.startsWith('/admin')) {
    return;
  }

  // 1. SAFETY: Never cache POST, dynamic endpoints, or external tracking
  if (request.method !== 'GET' || url.pathname.startsWith('/api') || url.pathname.includes('booking')) {
    return;
  }

  // 2. IMAGES: Cache First, bounded growth
  if (request.destination === 'image') {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        const fetchPromise = fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
            try {
              const clone = networkResponse.clone();
              caches.open(CACHES.images).then((cache) => {
                cache.put(request, clone).catch(() => {});
                trimCache(CACHES.images, IMAGE_CACHE_LIMIT);
              });
            } catch (_) { /* body already used */ }
          }
          return networkResponse;
        }).catch(() => null);
        return cachedResponse || fetchPromise;
      })
    );
    return;
  }

  // 3. HTML PAGES: Network First with Timeout!
  if (request.mode === 'navigate' || request.destination === 'document') {
    event.respondWith(
      new Promise((resolve) => {
        let timeoutTriggered = false;
        
        // Timeout racing
        const timeoutId = setTimeout(() => {
          timeoutTriggered = true;
          caches.match(request).then((cached) => {
            resolve(cached || caches.match(OFFLINE_URL));
          });
        }, NAVIGATION_NETWORK_TIMEOUT_MS);

        fetch(request).then((response) => {
          clearTimeout(timeoutId);
          if (!timeoutTriggered) {
            if (response && response.status === 200 && response.type === 'basic') {
              try {
                const responseClone = response.clone();
                caches.open(CACHES.html).then((cache) => cache.put(request, responseClone).catch(() => {}));
              } catch (_) { /* body already used */ }
            }
            resolve(response);
          }
        }).catch(() => {
          clearTimeout(timeoutId);
          if (!timeoutTriggered) {
            caches.match(request).then((cached) => {
              resolve(cached || caches.match(OFFLINE_URL));
            });
          }
        });
      })
    );
    return;
  }

  // 4. STATIC ASSETS (CSS/JS/Fonts): Stale-While-Revalidate
  if (['style', 'script', 'font'].includes(request.destination)) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        const fetchPromise = fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
            try {
              const clone = networkResponse.clone();
              caches.open(CACHES.static).then((cache) => cache.put(request, clone).catch(() => {}));
            } catch (_) { /* body already used */ }
          }
          return networkResponse;
        }).catch(() => null);
        
        // Return cached immediately if exists, update in background
        return cachedResponse || fetchPromise;
      })
    );
    return;
  }
});
