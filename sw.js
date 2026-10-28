const CACHE_NAME = 'replog-static-v1';
const STATIC_FILES = [
  './assets/styles.css',
  './assets/app.js',
  './assets/icon.svg',
  './manifest.webmanifest'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_FILES))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(names => Promise.all(
        names
          .filter(name => name.startsWith('replog-static-') && name !== CACHE_NAME)
          .map(name => caches.delete(name))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const requestUrl = new URL(request.url);

  // A API, o login e as páginas PHP nunca entram no cache do dispositivo.
  if (request.method !== 'GET' || requestUrl.origin !== self.location.origin || requestUrl.pathname.endsWith('.php')) {
    return;
  }

  event.respondWith(
    fetch(request)
      .then(response => {
        if (response.ok) {
          const responseCopy = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, responseCopy));
        }
        return response;
      })
      .catch(() => caches.match(request))
  );
});
