// Arquivo: service-worker.js
const cacheName = "ticket-sync-cache-v1";
const assets = [
  "/validar_ingresso.php",
  "/css/index.css",
  "/css/validar_ingresso.css",
  "/uploads/validartickets.png"
  // Adicione outros arquivos necessários (JS, imagens, etc.)
];

self.addEventListener("install", event => {
  // Força a ativação imediata do SW
  self.skipWaiting();
  event.waitUntil(
    caches.open(cacheName).then(cache => cache.addAll(assets))
  );
});

self.addEventListener("fetch", event => {
  event.respondWith(
    caches.match(event.request).then(response => response || fetch(event.request))
  );
});
