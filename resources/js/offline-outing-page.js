// Outings created offline: their page (appel + crew plans) is rebuilt on the device from the queued form,
// and the outings list shows them until they reach the server.
import { mountAttendance } from './attendance';
import { mountOfflineOuting } from './offline-outing';
import { pending } from './offline-queue';
import { escapeHtml as e } from './yole';

const isOfflineOuting = (op) => op.entity === 'form' && op.payload?.method === 'POST' && op.payload?.url === '/sorties' && op.payload?.fields?.uuid;

/** Local date (YYYY-MM-DD): toISOString() is UTC, a day ahead in Martinique from 8 pm. */
const localToday = () => { const d = new Date(); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); };

const formatDate = (iso) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) : '');

export async function mountOfflineOutingPage(root) {
    const uuid = new URLSearchParams(location.search).get('uuid');
    const queued = (await pending().catch(() => [])).find((op) => isOfflineOuting(op) && op.payload.fields.uuid === uuid);

    if (!queued) {
        // Already synced: open the real outing when the network is there.
        if (uuid && navigator.onLine) { location.replace(`/sorties/par-identifiant/${uuid}`); return; }
        root.querySelector('[data-offline-missing]').classList.remove('hidden');
        return;
    }

    const f = queued.payload.fields;
    const date = formatDate(f.date);
    root.querySelector('[data-offline-field="title"]').textContent = f.title || 'Sortie';
    root.querySelector('[data-offline-field="meta"]').textContent = [date, [f.start_time, f.end_time].filter(Boolean).join(' – '), f.location].filter(Boolean).join(' · ');
    document.title = `${f.title || 'Sortie'} — YoleTeam`;
    root.querySelector('[data-offline-outing-body]').classList.remove('hidden');

    const attendance = root.querySelector('[data-attendance]');
    attendance.dataset.outingUuid = uuid;
    attendance.dataset.outingLabel = `${f.title} · ${date}`;
    mountAttendance(attendance);

    const plans = root.querySelector('[data-outing-plans]');
    plans.dataset.outingUuid = uuid;
    const toNumber = (value) => (value === undefined || value === null || value === '' ? null : +value);
    mountOfflineOuting(plans, {
        title: f.title,
        wind: { dir: toNumber(f.wind_direction), kts: toNumber(f.wind_strength) },
        engagedBoats: Array.isArray(f.boats) ? f.boats : [],
        attendanceUrl: '#appel',
    });
}

/** Outings list: outings created on this device that are still waiting for the network. */
export async function mountOfflineOutingList(container) {
    const outings = (await pending().catch(() => [])).filter(isOfflineOuting);
    if (!outings.length) return;
    container.classList.remove('hidden');
    container.innerHTML = `<p class="text-[11px] font-bold uppercase muted mb-2">Créées hors ligne · en attente de synchronisation</p>
        <div class="card px-4 lg:px-5 divide-y divide-slate-100">${outings.map((op) => {
            const f = op.payload.fields;
            return `<a href="/sorties/hors-ligne?uuid=${e(f.uuid)}" class="flex items-center gap-4 py-3">
                <span class="chip bg-amber-100 text-amber-800">Hors ligne</span>
                <div class="flex-1 min-w-0"><p class="font-bold truncate">${e(f.title || 'Sortie')}</p><p class="text-xs muted">${e(formatDate(f.date))}${f.location ? ' · ' + e(f.location) : ''}</p></div>
                <span class="text-sm font-semibold text-navy-700">Appel & équipages →</span></a>`;
        }).join('')}</div>`;
}

/**
 * Offline, the menu shortcuts (Présences / Plan d'équipage) would open the page cached for the outing of the day
 * they were cached. When outings were created on this device, open the one of today (or the closest) instead.
 */
export function mountOfflineShortcuts() {
    document.addEventListener('click', async (event) => {
        const link = event.target.closest('a[href]');
        if (!link || navigator.onLine) return;
        const path = new URL(link.href, location.origin).pathname;
        if (path !== '/appel' && path !== '/equipage') return;

        const outings = (await pending().catch(() => [])).filter(isOfflineOuting);
        if (!outings.length) return;
        event.preventDefault();
        const today = localToday();
        const distance = (op) => Math.abs(new Date(op.payload.fields.date) - new Date(today));
        const best = outings.find((op) => op.payload.fields.date === today) ?? [...outings].sort((a, b) => distance(a) - distance(b))[0];
        location.href = `/sorties/hors-ligne?uuid=${encodeURIComponent(best.payload.fields.uuid)}${path === '/appel' ? '#appel' : ''}`;
    });

    // Cached appel of another day: say so, and point to outings created offline.
    const attendance = document.querySelector('[data-attendance]:not([data-deferred])');
    const date = attendance?.dataset.outingDate;
    if (attendance && date && !navigator.onLine && date !== localToday()) {
        const notice = document.createElement('div');
        notice.className = 'mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm p-3';
        notice.textContent = `Hors ligne : cet appel est celui du ${new Date(`${date}T12:00:00`).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}. Choisissez la bonne sortie ci-dessus, ou créez-la hors ligne depuis Sorties.`;
        attendance.prepend(notice);
    }
}

/** Outing pickers (Présences / Plan d'équipage) also list the outings created on this device. */
export async function mountOfflineOutingPickers() {
    const pickers = document.querySelectorAll('[data-outing-picker]');
    if (!pickers.length) return;
    const outings = (await pending().catch(() => [])).filter(isOfflineOuting);
    if (!outings.length) return;
    pickers.forEach((select) => {
        const group = document.createElement('optgroup');
        group.label = 'Créées hors ligne';
        outings.forEach((op) => {
            const f = op.payload.fields;
            group.append(new Option(`${formatDate(f.date)} · ${f.title || 'Sortie'}`, `/sorties/hors-ligne?uuid=${encodeURIComponent(f.uuid)}`));
        });
        select.prepend(group);
    });
}
