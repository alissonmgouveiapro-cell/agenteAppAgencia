// Arquivo: sw.js
const CACHE_NAME = 'bliss-os-v1';

// Instalação do Service Worker
self.addEventListener('install', (event) => {
    console.log('Service Worker: Instalado');
    self.skipWaiting();
});

// Ativação
self.addEventListener('activate', (event) => {
    console.log('Service Worker: Ativo');
});

// Interceptação de Rede (Estratégia: Network First)
// Tenta buscar na internet. Se cair a net, tenta o cache (opcional).
self.addEventListener('fetch', (event) => {
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});