// Offline mode: service worker registration, page pre-caching, sync of the queued operations,
// connection badge / banner and the Synchronisation screen.
import { acknowledge, pending, uuid } from './offline-queue';
import { escapeHtml as e } from './yole';
import { refreshCsrf, toast } from './http';

const PAGES_CACHE = 'yt-pages-v1';
const ICONS = {
    ok: 'M17.5 19H9a7 7 0 1 1 6.7-9h1.8a4.5 4.5 0 1 1 0 9M9 14l2 2 4-4',
    off: 'M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M5 13a10 10 0 0 1 5.2-2.8M19 13a10 10 0 0 0-2-1.6M2 8.8a15 15 0 0 1 4.2-2.6M22 8.8A15 15 0 0 0 10.7 5M12 20h.01',
    sync: 'M21 12a9 9 0 0 1-15.5 6.2L3 16M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M3 21v-5h5',
    clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M12 6v6l4 2',
};
const svg = (name, cls) => `<svg class="${cls} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${ICONS[name]}"/></svg>`;

const state = { syncing: false, error: null };

function deviceId() {
    try {
        let id = localStorage.getItem('yt-device');
        if (!id) { id = uuid(); localStorage.setItem('yt-device', id); }
        return id;
    } catch {
        return 'appareil-inconnu';
    }
}

const lastSync = () => { try { return localStorage.getItem('yt-last-sync'); } catch { return null; } };
const formatTime = (iso) => new Date(iso).toLocaleString('fr-FR', { weekday: 'short', hour: '2-digit', minute: '2-digit' });

// ---------- sync ----------

export async function flush({ manual = false } = {}) {
    const url = document.body.dataset.syncUrl;
    if (!url || state.syncing) return;
    const operations = await pending();
    if (!navigator.onLine) {
        if (manual) toast('Pas de réseau : la synchronisation reprendra automatiquement.', 'sun');
        return render();
    }
    if (!operations.length) {
        if (manual) toast('Tout est déjà synchronisé');
        return render();
    }

    state.syncing = true;
    state.error = null;
    render();
    try {
        const token = await refreshCsrf();
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            credentials: 'same-origin',
            body: JSON.stringify({
                device_id: deviceId(),
                sent_at: new Date().toISOString(),
                operations: operations.map(({ id, entity, entity_uuid, payload, client_updated_at }) => ({ id, entity, entity_uuid, payload, client_updated_at })),
            }),
        });
        if (!response.ok) throw new Error(response.status === 401 ? 'Session expirée : reconnectez-vous pour synchroniser.' : `Erreur ${response.status}`);
        const { results, synced_at: syncedAt } = await response.json();
        await acknowledge(results.map((r) => r.id));
        try { localStorage.setItem('yt-last-sync', syncedAt); } catch { /* private mode */ }

        const count = (status) => results.filter((r) => r.status === status).length;
        if (count('conflict')) toast(`${count('conflict')} conflit(s) à résoudre dans Synchronisation`, 'error');
        else if (count('rejected')) toast(`${count('rejected')} modification(s) refusée(s) : ${results.find((r) => r.status === 'rejected').message ?? ''}`, 'error');
        else toast(`${count('applied')} modification(s) synchronisée(s)`, 'green');

        if (document.querySelector('[data-sync-page]') && (count('conflict') || count('rejected'))) location.reload();
    } catch (error) {
        state.error = error.message === 'Failed to fetch' ? 'Serveur injoignable' : error.message;
        if (manual) toast(state.error, 'error');
    } finally {
        state.syncing = false;
        render();
    }
}

// ---------- badge, banner, Synchronisation screen ----------

async function render() {
    const operations = await pending();
    const count = operations.length;
    const online = navigator.onLine;

    const badge = document.querySelector('[data-sync-badge]');
    if (badge) {
        const [tone, icon, text] = !online ? ['bg-amber-100 text-amber-800', 'off', `Hors ligne${count ? ` · ${count}` : ''}`]
            : state.syncing ? ['bg-sky-100 text-sky-800', 'sync', 'Synchronisation…']
                : count ? ['bg-amber-100 text-amber-800', 'clock', `${count} en attente`]
                    : ['bg-emerald-50 text-emerald-700', 'ok', 'Synchronisé'];
        badge.className = `chip h-8 px-3 ${tone}`;
        badge.innerHTML = `${svg(icon, `w-4 h-4 ${state.syncing ? 'animate-spin' : ''}`)}<span class="hidden sm:inline">${e(text)}</span>`;
        badge.title = text;
    }
    document.querySelector('[data-offline-banner]')?.classList.toggle('hidden', online);
    document.querySelector('[data-offline-banner]')?.classList.toggle('flex', !online);
    document.querySelectorAll('[data-sync-count]').forEach((el) => { el.textContent = count; el.classList.toggle('hidden', !count); });

    const page = document.querySelector('[data-sync-page]');
    if (!page) return;

    const last = lastSync();
    page.querySelector('[data-sync-status-title]').textContent = !online ? 'Hors ligne' : state.syncing ? 'Synchronisation en cours…' : count ? 'Modifications en attente' : 'En ligne — tout est à jour';
    page.querySelector('[data-sync-status-text]').textContent = !online
        ? `${count} modification(s) enregistrée(s) sur cet appareil, envoyée(s) dès le retour du réseau.`
        : state.error ?? (count ? `${count} modification(s) à envoyer.` : `Dernière synchronisation : ${last ? formatTime(last) : 'jamais depuis cet appareil'}.`);
    const iconBox = page.querySelector('[data-sync-status-icon]');
    iconBox.className = `w-14 h-14 shrink-0 rounded-2xl grid place-items-center ${!online || count ? 'bg-amber-400 text-navy-950' : 'bg-emerald-500 text-white'}`;
    iconBox.innerHTML = svg(!online ? 'off' : count ? 'clock' : 'ok', 'w-7 h-7');
    page.querySelector('[data-sync-status-card]').classList.toggle('bg-amber-50', !online);

    page.querySelector('[data-sync-queue-count]').textContent = `${count} élément${count > 1 ? 's' : ''}`;
    page.querySelector('[data-sync-queue]').innerHTML = count
        ? operations.map((op) => `<a href="${e(op.url)}" class="px-5 py-3 flex items-center gap-3 hover:bg-slate-50">
              <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center bg-amber-100 text-amber-700">${svg('clock', 'w-4 h-4')}</span>
              <div class="flex-1 min-w-0"><p class="text-sm font-semibold truncate">${e(op.label)}</p>
                <p class="text-xs muted">${({ attendance: 'Présence', crew_plan: 'Plan d’équipage', form: 'Formulaire' })[op.entity] ?? 'Modification'} · modifié ${e(formatTime(op.client_updated_at))} sur cet appareil</p></div>
              <span class="chip bg-amber-100 text-amber-800">En attente</span></a>`).join('')
        : '<p class="px-5 pb-5 text-sm muted">Aucune modification en attente.</p>';

    const list = page.querySelector('[data-offline-pages]');
    if (list && 'caches' in window) {
        const cache = await caches.open(PAGES_CACHE);
        const paths = (await cache.keys()).map((request) => new URL(request.url).pathname);
        const kinds = {
            appel: paths.filter((p) => /\/appel$/.test(p)).length,
            plans: paths.filter((p) => /\/equipages\/\d+\/modifier$/.test(p)).length,
            sorties: paths.filter((p) => /^\/sorties\/\d+$/.test(p)).length,
        };
        list.innerHTML = paths.length
            ? [[kinds.sorties, 'sortie(s)'], [kinds.appel, 'appel(s)'], [kinds.plans, 'plan(s) d’équipage']]
                .map(([n, label]) => `<p class="flex items-center gap-2.5">${svg('ok', 'w-4 h-4 text-emerald-600')}${n} ${label}</p>`).join('')
            : '<p class="muted">Rien pour l’instant : ouvrez le tableau de bord avec du réseau.</p>';
    } else if (list) {
        list.innerHTML = '<p class="muted">Le mode hors ligne complet nécessite une connexion sécurisée (HTTPS).</p>';
    }
}

// ---------- service worker ----------

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    try {
        await navigator.serviceWorker.register('/sw.js');
        const registration = await navigator.serviceWorker.ready;
        const worker = registration.active;
        if (!worker) return;

        // Assets of this page (the first visit happened before the worker controlled it).
        const assets = [...document.querySelectorAll('link[href*="/build/"], script[src*="/build/"]')].map((el) => el.href || el.src);
        worker.postMessage({ type: 'cache-assets', urls: assets });

        // Dashboard: keep the appel and crew plans of the coming outings (at most every 10 minutes).
        const listed = document.querySelector('[data-offline-urls]');
        if (listed && navigator.onLine) {
            let recent = false;
            try { recent = Date.now() - (+localStorage.getItem('yt-precache') || 0) < 600000; } catch { /* ignore */ }
            if (!recent) {
                worker.postMessage({ type: 'cache-pages', urls: [location.pathname, ...JSON.parse(listed.textContent)] });
                try { localStorage.setItem('yt-precache', String(Date.now())); } catch { /* ignore */ }
            }
        }
    } catch (error) {
        console.warn('Service worker indisponible', error);
    }
}

export function mountSync() {
    // Signed out: forget the cached pages of the previous session.
    if (!document.body.dataset.userId) {
        if ('caches' in window) caches.delete(PAGES_CACHE);
        try { localStorage.removeItem('yt-precache'); } catch { /* ignore */ }
        return;
    }

    registerServiceWorker();

    let timer;
    window.addEventListener('yt:queue', () => {
        render();
        clearTimeout(timer);
        timer = setTimeout(() => flush(), 1500);
    });
    window.addEventListener('online', () => flush());
    window.addEventListener('offline', () => render());
    setInterval(() => { if (navigator.onLine) flush(); }, 60000);
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-sync-now]')) flush({ manual: true });
    });

    render();
    flush();
}
