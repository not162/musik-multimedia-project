/* Musikku PWA Service Worker (App Shell + Offline Audio Cache + Push Ready) */
const CACHE_SHELL   = 'musikku-shell-v2';
const CACHE_RUNTIME = 'musikku-runtime-v2';
const CACHE_AUDIO   = 'musikku-offline-audio-v1';

const APP_SHELL = [
  '/offline',
  '/css/musikku.css',
  '/js/player.js',
  '/js/pwa.js',
  '/js/realtime.js',
  '/js/vendor/pusher.min.js',
  '/js/vendor/echo.iife.js',
  '/manifest.webmanifest',
  '/images/icons/icon-192x192.png',
  '/images/icons/icon-512x512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_SHELL).then((cache) => cache.addAll(APP_SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  const keep = [CACHE_SHELL, CACHE_RUNTIME, CACHE_AUDIO];
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => !keep.includes(k)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

/* Handle partial Range requests cleanly for cached offline audio */
async function serveCachedAudio(request) {
  const cache = await caches.open(CACHE_AUDIO);
  const urlKey = request.url.split('?')[0];
  const cached = await cache.match(urlKey) || await cache.match(request);
  if (!cached) return null;

  const rangeHeader = request.headers.get('range');
  if (!rangeHeader) return cached;

  const buf = await cached.arrayBuffer();
  const match = /bytes=(\d+)-(\d*)/.exec(rangeHeader);
  const start = match ? Number(match[1]) : 0;
  const end = match && match[2] ? Number(match[2]) : buf.byteLength - 1;
  const sliced = buf.slice(start, end + 1);

  return new Response(sliced, {
    status: 206,
    statusText: 'Partial Content',
    headers: {
      'Content-Type': cached.headers.get('Content-Type') || 'audio/mpeg',
      'Content-Range': `bytes ${start}-${end}/${buf.byteLength}`,
      'Content-Length': String(sliced.byteLength),
      'Accept-Ranges': 'bytes',
    },
  });
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Never intercept broadcasting auth, admin panels, or API mutations/private routes
  if (
    url.pathname.startsWith('/admin') ||
    url.pathname.startsWith('/api/v1/admin') ||
    url.pathname.startsWith('/api/v1/me') ||
    url.pathname.startsWith('/broadcasting')
  ) {
    return;
  }

  // Audio streams: check offline saved audio cache first, else pass through network (preserving Range 206)
  if (url.pathname.includes('/stream')) {
    event.respondWith((async () => {
      const offlineHit = await serveCachedAudio(req);
      if (offlineHit) return offlineHit;
      return fetch(req);
    })());
    return;
  }

  // Static assets (CSS, JS, images, storage covers): Cache-First + background refresh
  if (
    url.pathname.startsWith('/css/') ||
    url.pathname.startsWith('/js/') ||
    url.pathname.startsWith('/images/') ||
    url.pathname.startsWith('/storage/covers/') ||
    url.pathname.startsWith('/storage/artists/') ||
    url.pathname === '/manifest.webmanifest'
  ) {
    event.respondWith(
      caches.match(req).then((cached) => {
        const fetched = fetch(req).then((res) => {
          if (res && res.status === 200) {
            const copy = res.clone();
            caches.open(CACHE_RUNTIME).then((c) => c.put(req, copy));
          }
          return res;
        }).catch(() => cached);
        return cached || fetched;
      })
    );
    return;
  }

  // HTML page navigation: Network-First, fallback to cached page or /offline
  if (req.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(req).then((res) => {
        if (res && res.status === 200 && !url.pathname.startsWith('/akun')) {
          const copy = res.clone();
          caches.open(CACHE_RUNTIME).then((c) => c.put(req, copy));
        }
        return res;
      }).catch(async () => {
        return (await caches.match(req)) || (await caches.match('/offline'));
      })
    );
  }
});

self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : { title: 'Musikku', body: 'Ada pembaruan musik baru!' };
  event.waitUntil(
    self.registration.showNotification(data.title || 'Musikku', {
      body: data.body || '',
      icon: '/images/icons/icon-192x192.png',
      badge: '/images/icons/icon-96x96.png',
      data: { url: data.url || '/' },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = event.notification.data?.url || '/';
  event.waitUntil(clients.openWindow(target));
});
