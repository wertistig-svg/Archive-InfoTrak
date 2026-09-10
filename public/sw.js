// Aucune mise en cache des pages privées ou des réponses API.
self.addEventListener('push', event => {
    let payload = {};
    try { payload = event.data?.json() || {}; } catch { /* secours */ }
    event.waitUntil(self.registration.showNotification(payload.title || 'InfoTrak', {
        body: payload.body || 'De nouvelles actualités vous attendent.', icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png', tag: payload.tag || 'infotrak-news', data: {url: payload.url || '/'},
    }));
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    let url = new URL('/', self.location.origin);
    try { const target = new URL(event.notification.data?.url || '/', self.location.origin); if (target.origin === self.location.origin) url = target; } catch { /* rester sur le fil */ }
    event.waitUntil(clients.openWindow(url.href));
});
