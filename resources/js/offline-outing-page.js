// Outings created offline: their page (appel + crew plans) is rebuilt on the device from the queued form,
// and the outings list shows them until they reach the server.
import { mountAttendance } from './attendance';
import { mountOfflineOuting } from './offline-outing';
import { pending } from './offline-queue';
import { escapeHtml as e } from './yole';

const isOfflineOuting = (op) => op.entity === 'form' && op.payload?.method === 'POST' && op.payload?.url === '/sorties' && op.payload?.fields?.uuid;

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
