const CACHE_NAME = 'yem-agri-v1';
const PRECACHE = [
  '/css/app.css',
  '/js/agri-ui.js',
  '/manifest.webmanifest',
  '/icons/icon.svg',
  '/geo/yem-woredas.geojson',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE).catch(() => {}))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  if (PRECACHE.includes(url.pathname)) {
    event.respondWith(
      caches.match(req).then((cached) => cached || fetch(req).then((res) => {
        const clone = res.clone();
        caches.open(CACHE_NAME).then((c) => c.put(req, clone));
        return res;
      }))
    );
    return;
  }

  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => new Response(
        '<!doctype html><html lang="am"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ኢንተርኔት የለም</title><style>body{font-family:sans-serif;padding:2rem;background:#f4f6f3;color:#1c2a22;max-width:520px;margin:auto}.card{background:#fff;border:1px solid #dfe5df;border-radius:10px;padding:1.5rem}</style></head><body><div class="card"><h1>🌾 ኢንተርኔት ተቋርጧል</h1><p>መስመር ላይ ሲመለሱ እንደገና ይሞክሩ። ያስቀመጡት ረቂቅ በመሣሪያዎ ላይ ተጠብቋል።</p><button onclick="location.reload()">እንደገና ሞክር</button></div></body></html>',
        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
      ))
    );
  }
});
