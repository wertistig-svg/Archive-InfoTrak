/*
 * InfoTrak — interactions du tableau d'actualités.
 * Compatible Symfony AssetMapper (module ES) + Turbo.
 * Filtres locaux + préférences persistées (POST /preferences)
 * + notifications (GET /api/notifications) + feedback (POST /api/feedback).
 */

async function postJson(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
        body: JSON.stringify(data),
    });
    const result = await response.json().catch(() => null);
    if (!response.ok || response.redirected) throw new Error(result?.error ?? 'Impossible d’enregistrer. Rechargez la page puis réessayez.');
    return result;
}

// Turbo clone les pages en cache sans leurs listeners. Un attribut HTML ne permet
// donc pas de savoir si le DOM courant a réellement été initialisé.
const initializedRoots = new WeakSet();
let pageListeners;

function initInfotrak() {
    const toast = document.querySelector('#toast');
    if (!toast) return;
    if (initializedRoots.has(toast)) return;
    initializedRoots.add(toast);
    pageListeners?.abort();
    pageListeners = new AbortController();
    const listenerOptions = { signal: pageListeners.signal };

    const showToast = (message) => {
        toast.textContent = message;
        toast.classList.add('show');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.remove('show'), 2800);
    };

    const stories = [...document.querySelectorAll('.story')];
    const topicButtons = [...document.querySelectorAll('.topic[data-topic]')];
    const zoneButtons = [...document.querySelectorAll('.zone-filter')];
    let activeZone = null;

    function filterStories(topic) {
        stories.forEach((story) => {
            const topicOk = !topic || topic === 'Tous' || story.dataset.category === topic;
            const zoneOk = !activeZone || story.dataset.place === activeZone;
            story.hidden = !(topicOk && zoneOk);
        });
        topicButtons.forEach((button) => button.classList.toggle('active', button.dataset.topic === topic));
        if (topic) showToast(topic === 'Tous' ? 'Toutes les actualités sont affichées.' : `Filtre activé : ${topic}`);
    }
    topicButtons.forEach((button) => button.addEventListener('click', () => filterStories(button.dataset.topic)));
    zoneButtons.forEach((button) =>
        button.addEventListener('click', () => {
            activeZone = activeZone === button.dataset.zone ? null : button.dataset.zone;
            zoneButtons.forEach((b) => b.classList.toggle('active', b.dataset.zone === activeZone));
            filterStories(document.querySelector('.topic.active')?.dataset.topic ?? 'Tous');
            if (activeZone) showToast(`Zone : ${activeZone}`);
        }),
    );

    // --- Tiroir latéral (hamburger) : glisse PAR-DESSUS le contenu, fluide et rapide ---
    // Il ne s'ouvre que sur demande (jamais tout seul) : fermé par défaut à chaque chargement.
    const sidebar = document.querySelector('#sidebar');
    const hamburger = document.querySelector('.mobile-menu');
    const backdrop = document.querySelector('#sidebarBackdrop');
    let drawerScroll = 0;
    const background = document.querySelector('.reader-main') ?? document.querySelector('main');

    function syncHamburger() {
        if (!hamburger || !sidebar) return;
        const open = document.body.classList.contains('sidebar-open');
        hamburger.setAttribute('aria-expanded', String(open));
        hamburger.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    }
    function openDrawer() {
        drawerScroll = window.scrollY;
        closeNotifPanel();
        closeUserMenu();
        document.body.classList.add('sidebar-open');
        document.body.style.position = 'fixed';
        document.body.style.top = `-${drawerScroll}px`;
        document.body.style.width = '100%';
        background?.setAttribute('inert', '');
        sidebar?.setAttribute('role', 'dialog');
        sidebar?.setAttribute('aria-modal', 'true');
        backdrop?.removeAttribute('hidden');
        syncHamburger();
        sidebar?.querySelector('button, a')?.focus();
    }
    function closeDrawer() {
        if (!document.body.classList.contains('sidebar-open')) return;
        document.body.classList.remove('sidebar-open');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        background?.removeAttribute('inert');
        sidebar?.removeAttribute('role');
        sidebar?.removeAttribute('aria-modal');
        backdrop?.setAttribute('hidden', '');
        window.scrollTo(0, drawerScroll);
        syncHamburger();
        hamburger?.focus({ preventScroll: true });
    }
    if (hamburger && sidebar) {
        syncHamburger();
        hamburger.addEventListener('click', () => {
            if (document.body.classList.contains('sidebar-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
        backdrop?.addEventListener('click', () => closeDrawer());
        // Choisir un lien/sujet referme le tiroir pour voir le contenu.
        sidebar.addEventListener('click', (event) => {
            if (event.target.closest('a,button')) closeDrawer();
        });
        sidebar.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab' || !document.body.classList.contains('sidebar-open')) return;
            const items = [...sidebar.querySelectorAll('a[href],button')].filter(el => el.getClientRects().length);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        });
        window.matchMedia('(min-width: 1101px)').addEventListener?.('change', () => {
            closeDrawer();
        }, listenerOptions);
    }

    // --- Modal préférences (persistées côté Symfony) ---
    const modal = document.querySelector('#preferencesModal');
    const openPreferences = document.querySelector('#openPreferences');
    const notificationButton = document.querySelector('#notificationButton');
    const modalClose = document.querySelector('.modal-close');
    const savePreferences = document.querySelector('#savePreferences, .save-preferences');
    const notifPanel = document.querySelector('#notifPanel');

    if (openPreferences && modal) openPreferences.addEventListener('click', () => modal.showModal());
    document.querySelectorAll('[data-open-preferences]').forEach((button) => button.addEventListener('click', () => modal?.showModal()));
    if (modalClose && modal) modalClose.addEventListener('click', () => modal.close());

    // La cloche ouvre le panneau de notifications (pas le modal).
    if (notificationButton && notifPanel) {
        notificationButton.addEventListener('click', async () => {
            notifPanel.hidden = !notifPanel.hidden;
            notificationButton.setAttribute('aria-expanded', String(!notifPanel.hidden));
            closeUserMenu();
            if (!notifPanel.hidden) await refreshNotifications();
        });
    }

    // --- Menu profil façon Reddit (avatar) ---
    const avatarButton = document.querySelector('#avatarButton');
    const userMenu = document.querySelector('#userMenu');

    function closeUserMenu() {
        if (!userMenu || userMenu.hidden) return;
        userMenu.hidden = true;
        avatarButton?.setAttribute('aria-expanded', 'false');
    }
    function closeNotifPanel() {
        if (!notifPanel || notifPanel.hidden) return;
        notifPanel.hidden = true;
        notificationButton?.setAttribute('aria-expanded', 'false');
    }

    if (avatarButton && userMenu) {
        avatarButton.addEventListener('click', () => {
            userMenu.hidden = !userMenu.hidden;
            avatarButton.setAttribute('aria-expanded', String(!userMenu.hidden));
            closeNotifPanel();
        });
        userMenu.querySelector('[data-menu="preferences"]')?.addEventListener('click', () => {
            closeUserMenu();
            modal?.showModal();
        });
        userMenu.querySelector('[data-menu="theme"]')?.addEventListener('click', () => {
            applyTheme(document.body.classList.contains('light') ? 'dark' : 'light');
            closeUserMenu();
            showToast(document.body.classList.contains('light') ? 'Mode clair activé.' : 'Mode sombre activé.');
        });
        userMenu.querySelector('[data-menu="read-all"]')?.addEventListener('click', async () => {
            try {
                await postJson('/api/notifications/read-all', {});
                await refreshNotifications();
                closeUserMenu();
                showToast('Toutes les notifications sont lues.');
            } catch {
                showToast('Notifications inaccessibles.');
            }
        });
    }

    // Fermeture au clic en dehors des boîtes + touche Escape (corrige le bug du panneau qui restait ouvert).
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (notifPanel && !notifPanel.hidden && !notifPanel.contains(target) && !notificationButton?.contains(target)) {
            closeNotifPanel();
        }
        if (userMenu && !userMenu.hidden && !userMenu.contains(target) && !avatarButton?.contains(target)) {
            closeUserMenu();
        }
    }, listenerOptions);
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeNotifPanel();
        closeUserMenu();
        closeDrawer();
    }, listenerOptions);

    // --- Mode d'affichage clair / sombre (persisté) ---
    function applyTheme(theme) {
        document.body.classList.toggle('light', theme === 'light');
        try {
            localStorage.setItem('infotrak-theme', theme);
        } catch {
            /* stockage indisponible : le choix vit le temps de la page */
        }
        document.querySelectorAll('[data-theme-label]').forEach((el) => {
            el.textContent = theme === 'light' ? 'Clair' : 'Sombre';
        });
    }
    let initialTheme = 'dark';
    try {
        initialTheme = localStorage.getItem('infotrak-theme') === 'light' ? 'light' : 'dark';
    } catch {
        initialTheme = document.body.classList.contains('light') ? 'light' : 'dark';
    }
    applyTheme(initialTheme);
    document.querySelector('#themeToggle')?.addEventListener('click', () => {
        applyTheme(document.body.classList.contains('light') ? 'dark' : 'light');
    });

    const toggleChoice = (selector) => {
        document.querySelectorAll(selector).forEach((button) =>
            button.addEventListener('click', () => {
                const row = button.parentElement;
                if (row.classList.contains('radio')) row.querySelectorAll('button').forEach((item) => item.classList.remove('selected'));
                button.classList.toggle('selected');
                row.querySelectorAll('button').forEach((item) => item.setAttribute('aria-pressed', String(item.classList.contains('selected'))));
            }),
        );
    };
    toggleChoice('#topicChoices button, #zoneChoices button, #frequencyChoices button, .choice-row button');

    if (savePreferences && modal) {
        savePreferences.addEventListener('click', async () => {
            savePreferences.disabled = true;
            const topics = [...document.querySelectorAll('#topicChoices button.selected')].map((b) => b.dataset.value ?? b.textContent.trim());
            const zones = [...document.querySelectorAll('#zoneChoices button.selected')].map((b) => b.dataset.value ?? b.textContent.trim());
            const frequency = document.querySelector('#frequencyChoices button.selected')?.dataset.value ?? 'important';
            try {
                const result = await postJson('/preferences', { topics, zones, frequency });
                modal.close();
                showToast(`Préférences enregistrées (${result.topics.length} sujets, ${result.zones.length} zones).`);
                window.setTimeout(() => { window.location.href = '/'; }, 600);
            } catch (error) {
                showToast(error.message || "Impossible d'enregistrer vos préférences.");
                savePreferences.disabled = false;
            }
        });
    }

    // --- Favoris (local, visuel) ---
    document.querySelectorAll('.save').forEach((button) =>
        button.addEventListener('click', () => {
            button.textContent = button.textContent === '♡' ? '♥' : '♡';
            showToast('Article ajouté à vos favoris.');
        }),
    );

    // --- Feedback "cette info vous intéresse ?" (persisté, anti-spam) ---
    document.querySelectorAll('.feedback-row').forEach((row) => {
        const articleId = Number(row.dataset.article);
        row.querySelectorAll('button[data-feedback]').forEach((button) =>
            button.addEventListener('click', async () => {
                const interested = button.dataset.feedback === '1';
                row.querySelectorAll('button').forEach((b) => { b.disabled = true; });
                try {
                    await postJson('/api/feedback', { articleId, interested });
                    row.querySelectorAll('button').forEach((b) => {
                        b.classList.toggle('active', b === button);
                        b.setAttribute('aria-pressed', String(b === button));
                    });
                    showToast(interested ? 'Votre intérêt a été enregistré. Merci !' : 'Article masqué dans votre fil.');
                    if (!interested) {
                        const story = row.closest('.story');
                        if (story) {
                            story.hidden = true;
                            story.dataset.dismissed = '1';
                            const count = document.querySelector('#visibleCount');
                            if (count) count.textContent = String(Math.max(0, Number(count.textContent) - 1));
                            const empty = document.querySelector('#feedEmpty');
                            if (empty) empty.hidden = stories.some((item) => !item.hidden);
                        }
                    }
                } catch {
                    showToast('Feedback non enregistré.');
                } finally {
                    row.querySelectorAll('button').forEach((b) => { b.disabled = false; });
                }
            }),
        );
    });

    // --- Page article façon X : like, partage ---
    const xBar = document.querySelector('[data-xbar]');
    if (xBar) {
        const articleId = Number(xBar.dataset.article);
        const likeBtn = xBar.querySelector('[data-x="like"]');
        const likesCount = xBar.querySelector('#likesCount');
        likeBtn?.addEventListener('click', async () => {
            const liked = !likeBtn.classList.contains('active');
            likeBtn.classList.toggle('active', liked);
            if (likesCount) {
                likesCount.textContent = String(Math.max(0, Number(likesCount.textContent || '0') + (liked ? 1 : -1)));
            }
            try {
                await postJson('/api/feedback', { articleId, interested: liked });
                showToast(liked ? 'Votre intérêt a été enregistré.' : 'Cet article sera masqué dans votre fil.');
            } catch {
                likeBtn.classList.toggle('active', !liked);
                if (likesCount) likesCount.textContent = String(Math.max(0, Number(likesCount.textContent) + (liked ? -1 : 1)));
                showToast('Vote non enregistré.');
            }
        });
        xBar.querySelector('[data-x="share"]')?.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(window.location.href);
                showToast('Lien copié !');
            } catch {
                showToast("Copie impossible, copiez l'adresse manuellement.");
            }
        });
        xBar.querySelector('[data-x="save"]')?.addEventListener('click', () => {
            showToast('Article ajouté à vos favoris.');
        });
        xBar.querySelector('[data-x="comments"]')?.addEventListener('click', () => {
            document.querySelector('#comments')?.scrollIntoView({ behavior: 'smooth' });
        });
    }

    // --- Commentaires (repliés par défaut, bouton pour déplier) ---
    const commentsBox = document.querySelector('#comments');
    if (commentsBox) {
        const articleId = Number(commentsBox.dataset.article);
        const list = commentsBox.querySelector('#commentList');
        const form = commentsBox.querySelector('#commentForm');
        const input = commentsBox.querySelector('#commentInput');
        const status = commentsBox.querySelector('#commentsStatus');
        const toggle = commentsBox.querySelector('#commentsToggle');
        const totals = [commentsBox.querySelector('#commentsTotal'), document.querySelector('#commentsCount')].filter(Boolean);

        function paintToggle() {
            if (!toggle) {
                if (list && list.hidden && list.querySelector('.comment')) list.hidden = false;
                return;
            }
            const total = list ? list.querySelectorAll('.comment').length : 0;
            if (total === 0) {
                toggle.hidden = true;
                return;
            }
            toggle.hidden = false;
            const open = list && !list.hidden;
            toggle.setAttribute('aria-expanded', String(open));
            toggle.textContent = open ? 'Masquer les commentaires' : `Voir les ${total} commentaire${total > 1 ? 's' : ''}`;
        }
        toggle?.addEventListener('click', () => {
            if (!list) return;
            list.hidden = !list.hidden;
            paintToggle();
        });

        function paintComment(item) {
            const row = document.createElement('div');
            row.className = 'comment';
            row.dataset.id = String(item.id);
            const avatar = document.createElement('span');
            avatar.className = 'avatar';
            avatar.textContent = item.initials;
            const body = document.createElement('div');
            body.className = 'comment-body';
            const head = document.createElement('small');
            head.textContent = `${item.displayName} · ${String(item.createdAt).slice(0, 16).replace('T', ' ')}`;
            const text = document.createElement('p');
            text.textContent = item.content;
            body.append(head, text);
            if (item.mine) {
                const del = document.createElement('button');
                del.className = 'comment-del';
                del.dataset.del = String(item.id);
                del.textContent = 'Supprimer';
                body.append(del);
            }
            row.append(avatar, body);
            return row;
        }

        async function loadComments() {
            if (status) {
                status.textContent = 'Chargement des commentaires…';
                status.hidden = false;
            }
            try {
                const data = await fetch(`/api/articles/${articleId}/comments`, {
                    headers: { Accept: 'application/json' },
                }).then((r) => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.json();
                });
                list.replaceChildren();
                data.items.forEach((item) => list.append(paintComment(item)));
                totals.forEach((el) => {
                    el.textContent = String(data.total);
                });
                if (data.total === 0 && status) {
                    status.textContent = 'Aucun commentaire pour le moment. Donnez votre avis !';
                    status.hidden = false;
                } else if (status) {
                    status.hidden = true;
                }
                paintToggle();
            } catch {
                if (status) {
                    status.textContent = 'Commentaires inaccessibles pour le moment.';
                    status.hidden = false;
                }
            }
        }

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const content = input.value.trim();
            if (!content) {
                showToast('Écrivez votre commentaire avant de publier.');
                return;
            }
            try {
                const data = await postJson(`/api/articles/${articleId}/comments`, { content });
                list.prepend(paintComment(data.item));
                list.hidden = false;
                input.value = '';
                totals.forEach((el) => {
                    el.textContent = String(Number(el.textContent || '0') + 1);
                });
                paintToggle();
                showToast('Commentaire publié.');
            } catch {
                showToast('Publication impossible (500 lettres max).');
            }
        });

        list?.addEventListener('click', async (event) => {
            const btn = event.target.closest('[data-del]');
            if (!btn) return;
            try {
                const response = await fetch(`/api/articles/${articleId}/comments/${btn.dataset.del}`, { method: 'DELETE', headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? '' } });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                btn.closest('.comment')?.remove();
                totals.forEach((el) => {
                    el.textContent = String(Math.max(0, Number(el.textContent || '0') - 1));
                });
                paintToggle();
                showToast('Commentaire supprimé.');
            } catch {
                showToast('Suppression impossible.');
            }
        });

        loadComments();
    }

    // --- Notifications ---
    async function refreshNotifications() {
        try {
            const data = await fetch('/api/notifications', { headers: { Accept: 'application/json' } }).then((r) => r.json());
            const list = document.querySelector('#notifList');
            const badge = document.querySelector('#notifCount');
            if (badge) {
                badge.textContent = String(data.unread);
                badge.hidden = data.unread === 0;
                badge.title = `${data.unread} notification(s) non lue(s)`;
            }
            if (list) {
                list.replaceChildren();
                if (data.items.length === 0) {
                    const empty = document.createElement('p');
                    const small = document.createElement('small');
                    small.textContent = 'Aucune notification.';
                    empty.append(small);
                    list.append(empty);
                }
                data.items.forEach((n) => {
                    const el = document.createElement('div');
                    el.className = `notif-item ${n.isRead ? '' : 'unread'}`;
                    el.dataset.id = String(n.id);

                    const title = document.createElement('strong');
                    title.textContent = n.title;
                    const summary = document.createElement('p');
                    summary.className = 'notif-summary';
                    summary.textContent = n.message;
                    const meta = document.createElement('small');
                    meta.textContent = `${n.source ? `${n.source} · ` : ''}${n.createdAt.slice(0, 16).replace('T', ' ')}`;
                    el.append(title, summary, meta);

                    if (n.link) {
                        el.dataset.link = n.link;
                        el.setAttribute('role', 'link');
                        el.tabIndex = 0;
                        el.title = "Lire l'article";
                        const read = document.createElement('span');
                        read.className = 'source-link';
                        read.textContent = ' · Lire →';
                        meta.append(read);
                    }
                    list.append(el);
                });
            }
        } catch {
            showToast('Notifications inaccessibles.');
        }
    }

    // Clic sur une notification avec article : marquer comme lue puis ouvrir la page
    // détaillée (délégué une seule fois : fonctionne aussi sur le rendu HTML initial).
    const notifList = document.querySelector('#notifList');
    notifList?.addEventListener('click', async (event) => {
        const el = event.target.closest('[data-link]');
        if (!el || !notifList.contains(el)) return;
        // Clic molette / Ctrl : ouverture classique dans un nouvel onglet, sans marquer.
        if (event.button !== 0 || event.ctrlKey || event.metaKey) return;
        event.preventDefault();
        const id = el.dataset.id;
        const link = el.dataset.link;
        try {
            await postJson(`/api/notifications/${id}/read`, {});
        } catch {
            /* lecture seule, la navigation prime */
        }
        window.location.href = link;
    });
    notifList?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        const el = event.target.closest('[data-link]');
        if (el) el.click();
    });

    document.querySelector('#markAllRead')?.addEventListener('click', async () => {
        try {
        await postJson('/api/notifications/read-all', {});
        await refreshNotifications();
        showToast('Toutes les notifications sont lues.');
        } catch { showToast('Impossible de marquer les notifications comme lues.'); }
    });


    // --- Recherche locale + recherche en direct du web (API, avec source et résumé) ---
    const searchInput = document.querySelector('#webSearchInput');
    const liveSection = document.querySelector('#liveResults');
    const liveList = document.querySelector('#liveList');
    const liveQuery = document.querySelector('#liveQuery');
    const liveStatus = document.querySelector('#liveStatus');
    const liveLabels = { news: 'ACTU', wiki: 'WIKI', place: 'LIEU', social: 'RÉSEAU' };
    const livePills = { news: 'web', wiki: 'wiki', place: 'place', social: 'social' };
    let liveTimer = null;
    let liveSeq = 0;

    document.addEventListener('turbo:before-cache', () => {
        if (liveTimer) window.clearTimeout(liveTimer);
        liveSeq += 1;
        window.clearTimeout(showToast.timer);
        toast.classList.remove('show');
        modal?.close();
        closeDrawer();
        closeNotifPanel();
        closeUserMenu();
    }, listenerOptions);

    function hideLiveResults() {
        if (liveTimer) window.clearTimeout(liveTimer);
        liveSeq += 1;
        if (liveSection) liveSection.hidden = true;
    }

    function renderLiveResults(results) {
        if (!liveList || !liveStatus) return;
        liveList.replaceChildren();
        if (results.length === 0) {
            liveStatus.textContent = 'Aucun résultat en direct pour cette recherche.';
            liveStatus.hidden = false;
            return;
        }
        liveStatus.hidden = true;
        results.forEach((item) => {
            const card = document.createElement('article');
            card.className = 'live-card story';
            card.dataset.category = item.source;
            card.dataset.place = '';

            const meta = document.createElement('div');
            meta.className = 'live-meta';
            const pill = document.createElement('span');
            pill.className = `pill ${livePills[item.type] ?? 'web'}`;
            pill.textContent = liveLabels[item.type] ?? 'WEB';
            const from = document.createElement('span');
            from.textContent = item.source;
            meta.append(pill, from);
            if (item.publishedAt) {
                const date = document.createElement('time');
                date.textContent = String(item.publishedAt).slice(0, 16).replace('T', ' ');
                meta.append(date);
            }

            const title = document.createElement('h3');
            const link = document.createElement('a');
            link.href = item.url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = item.title;
            title.append(link);

            const summary = document.createElement('p');
            summary.textContent = item.summary;

            const source = document.createElement('a');
            source.className = 'source-link';
            source.href = item.url;
            source.target = '_blank';
            source.rel = 'noopener';
            source.textContent = 'Consulter à la source →';

            card.append(meta, title, summary, source);
            liveList.append(card);
        });
    }

    async function runLiveSearch(query) {
        if (!liveSection || !liveList || !liveQuery || !liveStatus) return;
        const id = ++liveSeq;
        liveSection.hidden = false;
        liveQuery.textContent = query;
        liveList.replaceChildren();
        liveStatus.textContent = 'Recherche en direct…';
        liveStatus.hidden = false;
        try {
            const response = await fetch(`/api/search?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (id !== liveSeq) return;
            renderLiveResults(Array.isArray(data.results) ? data.results : []);
        } catch {
            if (id !== liveSeq) return;
            liveStatus.textContent = 'Recherche en direct indisponible pour le moment.';
            liveStatus.hidden = false;
        }
    }

    searchInput?.addEventListener('input', (event) => {
        const needle = event.target.value.trim().toLocaleLowerCase();
        if (liveTimer) window.clearTimeout(liveTimer);
        if (needle.length < 3) {
            hideLiveResults();
            return;
        }
        liveTimer = window.setTimeout(() => runLiveSearch(event.target.value.trim()), 650);
    });

    searchInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        const query = event.target.value.trim();
        if (query.length < 3) return;
        if (liveTimer) window.clearTimeout(liveTimer);
        runLiveSearch(query);
    });

    // --- Lecteur vidéo intégré (aucune redirection externe) ---
    // Les iframes sont chargées paresseusement : la src n'est posée qu'à l'ouverture,
    // et retirée à la fermeture pour couper le son. Une seule vidéo à la fois.
    function closePlayer(player) {
        const frame = player.querySelector('[data-video-frame]');
        const toggle = player.querySelector('[data-video-toggle]');
        if (!frame || frame.hidden) return;
        frame.hidden = true;
        toggle?.setAttribute('aria-expanded', 'false');
        if (toggle) toggle.textContent = '▶ Voir la vidéo';
        frame.querySelector('[data-video-tag]')?.pause?.();
        frame.querySelector('[data-video-iframe]')?.removeAttribute('src');
    }
    document.querySelectorAll('[data-video-player]').forEach((player) => {
        const toggle = player.querySelector('[data-video-toggle]');
        const frame = player.querySelector('[data-video-frame]');
        if (!toggle || !frame) return; // mode 'full' : déjà affiché, rien à brancher.
        toggle.addEventListener('click', () => {
            const willOpen = frame.hidden;
            document.querySelectorAll('[data-video-player]').forEach((other) => {
                if (other !== player) closePlayer(other);
            });
            if (!willOpen) {
                closePlayer(player);
                return;
            }
            frame.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            toggle.textContent = '✕ Masquer la vidéo';
            const iframe = frame.querySelector('[data-video-iframe]');
            if (iframe && !iframe.getAttribute('src')) iframe.setAttribute('src', iframe.dataset.src);
        });
    });

    if (notifPanel) refreshNotifications();
}

initInfotrak();
document.addEventListener('turbo:load', initInfotrak);

document.addEventListener('turbo:load', () => { if (location.hash === '#preferences') document.querySelector('#preferencesModal')?.showModal(); });
