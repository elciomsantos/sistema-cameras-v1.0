/**
 * Service Worker para Progressive Web App (PWA)
 * v5 - Lê sw-assets.json pós-build para pré-cachear assets Vite com hash
 */

const CACHE_NAME = 'cameras-app-v6';
const API_CACHE = 'cameras-api-v6';
const ASSETS_CACHE = 'cameras-assets-v6';

let BASE_URL = '';

self.addEventListener('message', event => {
  if (event.data && event.data.type === 'INIT_BASE_URL') {
    BASE_URL = event.data.baseUrl || '';
  }
});

self.addEventListener('install', event => {
  event.waitUntil(
    Promise.all([
      caches.open(CACHE_NAME).then(async cache => {
        try {
          const resp = await fetch('./sw-assets.json');
          const urls = await resp.json();
          await cache.addAll(urls);
          console.log('[SW] Pré-cache de', urls.length, 'assets via sw-assets.json');
        } catch (err) {
          console.error('[SW] Falha ao pré-carregar sw-assets.json:', err);
          await cache.addAll([
            './index.php',
            './offline.html',
            './manifest.json',
            './images/favicon-32x32.png',
            './images/favicon-16x16.png',
            './images/apple-touch-icon.png'
          ]);
        }
      }),
      caches.open(ASSETS_CACHE).then(cache => {
        return cache.addAll([
          'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
        ]).catch(err => {
          console.error('[SW] Failed to cache CDN assets:', err);
        });
      })
    ]).catch(err => {
      console.error('[SW] Install failed:', err);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME &&
              cacheName !== API_CACHE &&
              cacheName !== ASSETS_CACHE) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;

  // API requests - Network only, never cache
  if (url.pathname.includes('/api/') || url.search.includes('page=api/')) {
    event.respondWith(fetch(request));
    return;
  }

  // Navegação (HTML) - SEMPRE do servidor, nunca do cache
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match('./offline.html').then(resp => resp || new Response('Sem conexão.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=UTF-8' } }))
      )
    );
    return;
  }

  // Assets estáticos (css, js, imagens) - Cache-first para assets Vite com hash, network-first para o resto
  const isViteHashedAsset = /\/assets\/.+\.[a-f0-9]{8}\.(js|css|woff2?|ttf|eot|png|jpe?g|gif|svg|webp)$/.test(url.pathname);

  if (isViteHashedAsset) {
    event.respondWith(
      caches.match(request).then(cached => {
        if (cached) return cached;
        return fetch(request).then(response => {
          if (response && response.status === 200 && response.type === 'basic') {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, responseToCache));
          }
          return response;
        });
      })
    );
    return;
  }

  event.respondWith(
    fetch(request).then(response => {
      if (!response || response.status !== 200 || response.type !== 'basic') {
        return response;
      }
      const responseToCache = response.clone();
      caches.open(CACHE_NAME).then(cache => cache.put(request, responseToCache));
      return response;
    }).catch(() =>
      caches.match(request).then(cached => {
        if (cached) return cached;
        return caches.match('./index.php').then(cached =>
          cached || new Response('Sem conexão.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=UTF-8' } })
        );
      })
    )
  );
});

self.addEventListener('sync', event => {
  if (event.tag === 'sync-api') {
    if (!BASE_URL) {
      console.error('[SW] BASE_URL not initialized — postMessage INIT_BASE_URL first');
      return;
    }
    event.waitUntil(
      fetch(BASE_URL + '/index.php?page=api/api_ping', { method: 'GET' })
        .catch(err => console.error('[SW] Sync failed:', err))
    );
  }
});

self.addEventListener('push', event => {
  const options = {
    body: event.data ? event.data.text() : 'Notificação do Sistema',
    icon: '/images/android-chrome-192x192.png',
    badge: '/images/favicon-32x32.png',
    vibrate: [100, 50, 100],
    data: { dateOfArrival: Date.now(), primaryKey: 1 }
  };
  event.waitUntil(self.registration.showNotification('Sistema de Câmeras', options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then(clientList => {
      for (const client of clientList) {
        if (client.url === '/' && 'focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow('/');
    })
  );
});
