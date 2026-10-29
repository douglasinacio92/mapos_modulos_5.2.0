// Módulos Daitech: service worker do aplicativo instalável (PWA).
//
// Não guarda páginas do sistema em cache: elas têm dados de clientes e mudam o
// tempo todo. Só mostra a página "sem conexão" quando a internet cai.
const CACHE = 'daitech-os-v1';
const OFFLINE = new URL('assets/app/offline.html', self.registration.scope).href;
const ARQUIVOS = [OFFLINE, new URL('assets/app/offline.css', self.registration.scope).href, new URL('assets/img/app/icone-192.png', self.registration.scope).href];

self.addEventListener('install', (evento) => {
    evento.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(ARQUIVOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((nomes) => Promise.all(nomes.filter((nome) => nome !== CACHE).map((nome) => caches.delete(nome))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evento) => {
    const pedido = evento.request;
    if (pedido.mode === 'navigate') {
        evento.respondWith(fetch(pedido).catch(() => caches.match(OFFLINE)));
        return;
    }
    if (ARQUIVOS.includes(pedido.url)) {
        evento.respondWith(caches.match(pedido).then((resposta) => resposta ?? fetch(pedido)));
    }
});
