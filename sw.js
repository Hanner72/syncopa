// sw.js – minimaler Service Worker nur für die Installierbarkeit (PWA).
// Es wird bewusst nichts zwischengespeichert, damit sich am Verhalten der App nichts ändert
// (die Seiten sind serverseitig gerendert und session-abhängig).

self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    self.clients.claim();
});

self.addEventListener('fetch', function(event) {
    event.respondWith(fetch(event.request));
});
