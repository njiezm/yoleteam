// Appel (attendance) page: one tap = one saved status, with counters kept in sync.
// Offline, taps are kept in the device queue (offline-queue.js) and replayed by sync.js.
import { send, toast } from './http';
import { enqueue, forget, pending } from './offline-queue';

const STATUSES = ['present', 'absent', 'excuse', 'retard'];

export function mountAttendance(root) {
    const url = root.dataset.url;
    const outingUuid = root.dataset.outingUuid;
    const outingLabel = root.dataset.outingLabel;
    const form = root.querySelector('#attendance-form');
    const saveState = root.querySelector('[data-save-state]');
    const statuses = {}; // member id => status value | null
    const names = {};
    const labels = {};
    const keyFor = (memberId) => `attendance:${outingUuid}:${memberId}`;

    root.querySelectorAll('[data-member-row]').forEach((row) => {
        const id = row.dataset.memberRow;
        const on = row.querySelector('[aria-pressed="true"]');
        statuses[id] = on ? on.dataset.status : null;
        names[id] = row.dataset.label;
    });
    root.querySelectorAll('[data-member-row]:first-child [data-status]').forEach((btn) => { labels[btn.dataset.status] = btn.title; });

    const paint = (memberId) => {
        const row = root.querySelector(`[data-member-row="${memberId}"]`);
        if (!row) return;
        row.classList.toggle('bg-amber-50/40', !statuses[memberId]);
        row.querySelectorAll('[data-status]').forEach((btn) => {
            const on = statuses[memberId] === btn.dataset.status;
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.value = on ? '' : btn.dataset.status;
            btn.style.background = on ? btn.dataset.color : '';
            btn.classList.toggle('text-white', on);
            btn.classList.toggle('border-transparent', on);
            btn.classList.toggle('shadow-sm', on);
            btn.classList.toggle('bg-white', !on);
            btn.classList.toggle('border-slate-200', !on);
            btn.classList.toggle('text-slate-500', !on);
        });
    };

    const paintCounts = (counts) => {
        const local = counts ?? Object.fromEntries(STATUSES.map((s) => [s, Object.values(statuses).filter((v) => v === s).length]));
        Object.entries(local).forEach(([key, value]) => {
            const el = root.querySelector(`[data-count="${key}"]`);
            if (el) el.textContent = value;
        });
        root.querySelector('[data-count="none"]').textContent = Object.values(statuses).filter((s) => !s).length;
    };

    const keepOffline = async (memberIds) => {
        await Promise.all(memberIds.map((id) => enqueue({
            key: keyFor(id),
            entity: 'attendance',
            entity_uuid: outingUuid,
            payload: { member_id: +id, status: statuses[id] },
            label: `${names[id] ?? 'Membre'} → ${statuses[id] ? labels[statuses[id]] : 'non pointé'} (${outingLabel})`,
        })));
        paintCounts();
        saveState.textContent = 'Enregistré sur l’appareil — synchronisation au retour du réseau.';
    };

    const save = async (payload, previous) => {
        if (!url) {
            await keepOffline(Object.keys(previous).filter((id) => previous[id] !== statuses[id]));
            return;
        }
        saveState.textContent = 'Enregistrement…';
        try {
            const data = await send(url, 'PUT', payload);
            // Saved online: a queued older change for the same members must not be replayed later.
            await Promise.all(Object.keys(previous).map((id) => forget(keyFor(id))));
            Object.keys(statuses).forEach((id) => { statuses[id] = data.statuses[id] ?? null; paint(id); });
            paintCounts(data.counts);
            saveState.textContent = `Enregistré à ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
        } catch (error) {
            if (error.offline) {
                await keepOffline(Object.keys(previous).filter((id) => previous[id] !== statuses[id]));
                return;
            }
            Object.assign(statuses, previous);
            Object.keys(previous).forEach(paint);
            saveState.textContent = 'Échec de l’enregistrement — réessayez.';
            toast(error.message || 'Échec de l’enregistrement', 'error');
        }
    };

    form.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-status]');
        if (!btn) return;
        event.preventDefault();
        const id = btn.dataset.member;
        const previous = { [id]: statuses[id] };
        statuses[id] = statuses[id] === btn.dataset.status ? null : btn.dataset.status;
        paint(id);
        save({ statuses: { [id]: statuses[id] ?? '' } }, previous);
    });

    document.querySelectorAll('[data-all-present]').forEach((btn) => btn.addEventListener('click', (event) => {
        event.preventDefault();
        const previous = {};
        Object.keys(statuses).forEach((id) => {
            if (!statuses[id]) { previous[id] = null; statuses[id] = 'present'; paint(id); }
        });
        if (Object.keys(previous).length) save({ all_present: true }, previous);
    }));

    root.querySelector('[data-search]')?.addEventListener('input', (event) => {
        const q = event.target.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        root.querySelectorAll('[data-member-row]').forEach((row) => {
            row.hidden = q !== '' && !row.dataset.name.includes(q);
        });
    });

    // Changes made offline earlier on this device (the page may come from the offline cache).
    pending().then((operations) => {
        const mine = operations.filter((op) => op.entity === 'attendance' && op.entity_uuid === outingUuid);
        mine.forEach((op) => {
            const id = String(op.payload.member_id);
            if (id in statuses) { statuses[id] = op.payload.status; paint(id); }
        });
        if (mine.length) {
            paintCounts();
            saveState.textContent = `${mine.length} changement(s) enregistré(s) sur l’appareil, en attente de synchronisation.`;
        }
    }).catch(() => {});
}
