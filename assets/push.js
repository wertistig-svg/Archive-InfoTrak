function initPush() {
    const root = document.querySelector('[data-push-page]');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';
    const enable = document.querySelector('#pushEnable'), test = document.querySelector('#pushTest'), disable = document.querySelector('#pushDisable'), status = document.querySelector('#pushStatus');
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        enable.disabled = true;
        status.textContent = 'Ce navigateur ne permet pas les notifications ici. Sur iPhone, ouvrez InfoTrak depuis l’écran d’accueil. Sur Android, essayez Chrome.';
        return;
    }
    let registration;
    const request = async (url, data) => {
        const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(data)});
        const body = await response.json();
        if (!response.ok) throw new Error(body.error || 'Action impossible. Rechargez la page puis réessayez.');
        return body;
    };
    const display = enabled => { enable.hidden = enabled; test.hidden = !enabled; disable.hidden = !enabled; };
    const ready = navigator.serviceWorker.register('/sw.js').then(async r => {
        registration = r;
        await navigator.serviceWorker.ready;
        const subscription = await r.pushManager.getSubscription();
        display(!!subscription);
        status.textContent = subscription ? 'Notifications autorisées sur cet appareil. Envoyez un essai pour vérifier la réception.' : 'Notifications désactivées. L’activation nécessite votre accord.';
    }).catch(() => { status.textContent = 'Impossible de préparer les notifications. Rechargez la page.'; enable.disabled = true; });
    enable.addEventListener('click', async () => {
        enable.disabled = true;
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error('Autorisation refusée. Vous pouvez la modifier dans les réglages du navigateur.');
            await ready;
            const response = await fetch('/api/push/key');
            if (!response.ok) throw new Error('Service momentanément indisponible.');
            const {publicKey} = await response.json();
            const key = Uint8Array.from(atob(publicKey.replace(/-/g,'+').replace(/_/g,'/')), c => c.charCodeAt(0));
            const subscription = await registration.pushManager.subscribe({userVisibleOnly:true, applicationServerKey:key});
            try { await request('/api/push/subscribe', subscription.toJSON()); }
            catch (e) { await subscription.unsubscribe(); throw e; }
            display(true); status.textContent = 'Notifications activées. Vous pouvez maintenant envoyer un essai.';
        } catch (e) { status.textContent = e.message; } finally { enable.disabled = false; }
    });
    test.addEventListener('click', async () => {
        test.disabled = true;
        try { const s = await registration.pushManager.getSubscription(); await request('/api/push/test', {endpoint:s?.endpoint}); status.textContent = 'Essai transmis au service du navigateur. Vérifiez les notifications du téléphone.'; }
        catch (e) { status.textContent = e.message; } finally { test.disabled = false; }
    });
    disable.addEventListener('click', async () => {
        disable.disabled = true;
        try { const s = await registration.pushManager.getSubscription(); if (s) { await request('/api/push/unsubscribe', {endpoint:s.endpoint}); await s.unsubscribe(); } display(false); status.textContent = 'Notifications désactivées sur cet appareil.'; }
        catch (e) { status.textContent = e.message; } finally { disable.disabled = false; }
    });
}
document.addEventListener('turbo:load', initPush);
document.addEventListener('DOMContentLoaded', initPush);
document.addEventListener('turbo:before-cache', () => document.querySelector('[data-push-page]')?.removeAttribute('data-ready'));
initPush();
