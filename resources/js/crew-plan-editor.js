// Crew plan editor: seat members on the yole (drag & drop on desktop, tap a seat then a member on mobile),
// switch configuration (1 / 2 voiles), set wind and bwa placement. The full plan state is autosaved.
import { balance, escapeHtml as e, yoleSVG } from './yole';
import { send, toast } from './http';
import { enqueue, forget, pending } from './offline-queue';

const ROLE_FILTERS = ['tous', 'patron', 'aide_patron', 'premiere_corde', 'ecoute', 'dresseur', 'ecopeur'];
const PLACEMENTS = { interieur: 'Intérieur', milieu: 'Milieu', exterieur: 'Extérieur' };
const ICON = {
    x: 'M18 6 6 18M6 6l12 12',
    trash: 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6',
    scale: 'M12 3v18M5 21h14M3 7h18M6 7l-3 7a3 3 0 0 0 6 0zM18 7l-3 7a3 3 0 0 0 6 0z',
    alert: 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0M12 9v4M12 17h.01',
    grip: 'M9 5h.01M9 12h.01M9 19h.01M15 5h.01M15 12h.01M15 19h.01',
};
const icon = (name, cls = 'w-4 h-4') => `<svg class="${cls} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${ICON[name]}"/></svg>`;
const kg = (value) => `${Math.round(value)} kg`;

export function mountCrewPlanEditor(root) {
    const data = JSON.parse(root.dataset.crewEditor);
    const { roles, configurations, members } = data;
    const memberById = new Map(members.map((m) => [m.id, m]));
    const drawingMembers = Object.fromEntries(members.map((m) => [m.id, { initials: m.initials, short: m.short }]));

    const S = {
        configId: data.plan.configuration_id,
        assignments: { ...data.assignments },
        selected: null,
        roleFilter: 'tous',
        search: '',
        wind: { dir: data.plan.wind_direction, kts: data.plan.wind_strength },
        status: data.plan.status,
        version: data.plan.version,
        dirty: false,
        offline: false,
        timer: null,
        pending: null,
    };

    const $ = (sel) => root.querySelector(sel);
    const $$ = (sel, scope = document) => [...scope.querySelectorAll(sel)];
    const config = () => configurations.find((c) => c.id === S.configId) || configurations[0];
    const positions = () => config().positions;
    const positionByCode = (code) => positions().find((p) => p.code === code);
    const codeOf = (memberId) => Object.keys(S.assignments).find((code) => S.assignments[code].member_id === memberId);
    const assignedIds = () => new Set(Object.values(S.assignments).map((a) => a.member_id));
    const isMobile = () => window.innerWidth < 1024;

    const onSite = (m) => m.status === 'present' || m.status === 'retard';
    const pool = () => members.filter((m) => !data.attendanceRecorded || onSite(m) || codeOf(m.id));

    // ---------- persistence ----------
    const payload = () => ({
        boat_configuration_id: S.configId,
        wind_direction: S.wind.dir === '' || S.wind.dir === null ? null : +S.wind.dir,
        wind_strength: S.wind.kts === '' || S.wind.kts === null ? null : +S.wind.kts,
        assignments: Object.entries(S.assignments)
            .map(([code, a]) => ({ position: positionByCode(code), a }))
            .filter(({ position }) => position)
            .map(({ position, a }) => ({ position_id: position.id, member_id: a.member_id, bwa_placement: position.role === 'dresseur' ? (a.placement || null) : null })),
    });

    const setSaveState = (text) => $$('[data-save-state]').forEach((el) => { el.textContent = text; });

    const queueKey = `crew_plan:${data.plan.uuid}`;

    /** @returns {Promise<'saved'|'offline'|false>} */
    const saveNow = async () => {
        clearTimeout(S.timer);
        if (S.pending) await S.pending.catch(() => {});
        if (!S.dirty) return S.offline ? 'offline' : 'saved';
        S.dirty = false;
        setSaveState('Enregistrement…');
        const state = payload();
        S.pending = send(data.plan.update_url, 'PUT', state);
        try {
            const result = await S.pending;
            S.status = result.status;
            S.version = result.version;
            S.offline = false;
            await forget(queueKey);
            setSaveState('Enregistré');
            renderStatus();
            return 'saved';
        } catch (error) {
            if (error.offline) {
                // No network: keep the whole plan state on the device, sync.js replays it later.
                await enqueue({ key: queueKey, entity: 'crew_plan', entity_uuid: data.plan.uuid, payload: state, label: `Plan d’équipage · ${data.plan.label}` });
                S.offline = true;
                setSaveState('Sur l’appareil');
                return 'offline';
            }
            S.dirty = true;
            setSaveState('Non enregistré');
            toast(error.message || 'Échec de l’enregistrement', 'error');
            return false;
        } finally {
            S.pending = null;
        }
    };

    const changed = () => {
        S.dirty = true;
        setSaveState('Modifié…');
        clearTimeout(S.timer);
        S.timer = setTimeout(saveNow, 600);
        render();
    };

    window.addEventListener('beforeunload', (event) => {
        if (S.dirty) event.preventDefault();
    });

    // ---------- mutations ----------
    function assign(code, memberId) {
        const member = memberById.get(memberId);
        if (!member || member.elsewhere) return;
        const from = codeOf(memberId);
        if (from === code) return;
        const target = S.assignments[code];
        if (from) {
            delete S.assignments[from];
            // Dropping a seated member on an occupied seat swaps the two.
            if (target) S.assignments[from] = { member_id: target.member_id, placement: S.assignments[from]?.placement ?? null };
        }
        S.assignments[code] = { member_id: memberId, placement: target?.placement ?? null };
        toast(`${member.short} → ${positionByCode(code).label}`);
        changed();
    }

    function switchConfiguration(id) {
        S.configId = id;
        const codes = new Set(positions().map((p) => p.code));
        Object.keys(S.assignments).forEach((code) => { if (!codes.has(code)) delete S.assignments[code]; });
        S.selected = null;
        changed();
    }

    // ---------- rendering ----------
    const stats = () => balance(positions(), S.assignments, (id) => memberById.get(id));

    function renderStatus() {
        $$('[data-status-chip]').forEach((el) => {
            const validated = S.status === 'valide';
            el.className = `chip ${validated ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`;
            el.textContent = validated ? `Validé · v${S.version}` : `Brouillon · v${S.version}`;
        });
    }

    function renderProgress(b) {
        const pct = b.positions ? Math.round((b.filled / b.positions) * 100) : 0;
        $$('[data-progress-text]').forEach((el) => { el.textContent = `${b.filled}/${b.positions} postes`; });
        $$('[data-progress-bar]').forEach((el) => { el.style.width = `${pct}%`; });
        const warn = Math.abs(b.diff) > 12;
        $$('[data-balance-chip]').forEach((el) => {
            el.className = `chip ${warn ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`;
            el.innerHTML = `${icon('scale', 'w-3.5 h-3.5')}${warn ? kg(Math.abs(b.diff)) : 'OK'}`;
        });
    }

    function balanceCard(b) {
        const warn = Math.abs(b.diff) > 12;
        const shift = Math.max(-45, Math.min(45, b.diff * 1.5));
        return `<div class="card p-4">
            <div class="flex items-center justify-between"><p class="font-bold flex items-center gap-2">${icon('scale')}Équilibre</p>
              <span class="chip whitespace-nowrap ${warn ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">${warn ? 'À surveiller' : 'Équilibré'}</span></div>
            <div class="mt-3 flex justify-between text-xs font-bold"><span>Bâbord · ${kg(b.babord)}</span><span>${kg(b.tribord)} · Tribord</span></div>
            <div class="mt-2 relative h-3 rounded-full bg-slate-100"><div class="absolute top-0 bottom-0 left-1/2 w-px bg-slate-400"></div>
              <div class="absolute -top-1 w-5 h-5 rounded-full border-4 border-white shadow ${warn ? 'bg-amber-500' : 'bg-emerald-500'}" style="left:calc(${50 - shift}% - 10px)"></div></div>
            <div class="mt-3 grid grid-cols-3 gap-1.5 text-center text-sm">
              <div class="rounded-lg bg-slate-50 p-2"><p class="text-[10px] muted font-bold uppercase">Avant</p><p class="font-extrabold whitespace-nowrap">${kg(b.avant)}</p></div>
              <div class="rounded-lg bg-slate-50 p-2"><p class="text-[10px] muted font-bold uppercase">Arrière</p><p class="font-extrabold whitespace-nowrap">${kg(b.arriere)}</p></div>
              <div class="rounded-lg bg-slate-50 p-2"><p class="text-[10px] muted font-bold uppercase">Total</p><p class="font-extrabold whitespace-nowrap">${kg(b.total)}</p></div>
            </div>
            ${warn ? `<p class="mt-3 text-[12px] text-amber-800 bg-amber-50 rounded-lg p-2 flex gap-1.5">${icon('alert')}Écart de ${kg(Math.abs(b.diff))} côté ${b.diff > 0 ? 'bâbord' : 'tribord'}.</p>` : ''}
            <p class="mt-2 text-[11px] muted">Indication basée sur les poids déclarés — ne remplace pas l’œil du patron.</p>
          </div>`;
    }

    const placementControl = (code) => {
        const current = S.assignments[code]?.placement || 'milieu';
        return `<div class="seg w-full">${Object.entries(PLACEMENTS).map(([key, label]) => `<button type="button" data-place="${key}" class="flex-1 justify-center ${current === key ? 'on' : ''}">${label}</button>`).join('')}</div>`;
    };

    function memberCard(m) {
        const seat = codeOf(m.id);
        const sel = S.selected && positionByCode(S.selected);
        const fits = sel && m.roles.includes(sel.role);
        const blocked = Boolean(m.elsewhere);
        const cls = blocked ? 'border-transparent bg-slate-50 opacity-50 cursor-not-allowed'
            : seat ? 'border-transparent bg-slate-50 opacity-70 cursor-grab'
                : fits ? 'border-sun-400 bg-sun-100/40 cursor-grab' : 'border-slate-200 bg-white hover:border-navy-300 cursor-grab';
        const sub = blocked ? `Sur ${e(m.elsewhere)}` : seat ? `Placé · ${e(positionByCode(seat)?.label)}` : e(m.level);
        return `<div draggable="${!blocked}" data-member="${m.id}" role="button" tabindex="0" class="flex items-center gap-3 p-2.5 rounded-xl border ${cls}">
            <span class="text-slate-300 hidden lg:block">${icon('grip')}</span>
            <span class="w-9 h-9 text-xs rounded-full grid place-items-center font-bold text-white shrink-0" style="background:${e(m.color)}">${e(m.initials)}</span>
            <div class="flex-1 min-w-0"><p class="text-sm font-bold truncate">${e(m.short)}${m.status === 'retard' ? ' <span class="text-amber-600" title="En retard">◷</span>' : ''}</p>
              <p class="text-[11px] muted truncate">${m.roles.map((r) => e(roles[r]?.label)).join(' · ') || 'Sans poste'}</p></div>
            <div class="text-right shrink-0"><p class="text-xs font-bold">${m.kg ? kg(m.kg) : '—'}</p><p class="text-[10px] muted max-w-24 truncate">${sub}</p></div></div>`;
    }

    function filteredMembers() {
        const sel = S.selected && positionByCode(S.selected);
        const q = S.search.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        const assigned = assignedIds();
        return pool()
            .filter((m) => S.roleFilter === 'tous' || m.roles.includes(S.roleFilter) || (S.roleFilter === 'premiere_corde' && m.roles.includes('deuxieme_corde')))
            .filter((m) => !q || m.name.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().includes(q))
            .sort((a, z) => (Boolean(a.elsewhere) - Boolean(z.elsewhere))
                || (assigned.has(a.id) - assigned.has(z.id))
                || (sel ? z.roles.includes(sel.role) - a.roles.includes(sel.role) : 0)
                || (sel ? a.roles.indexOf(sel.role) - z.roles.indexOf(sel.role) : 0));
    }

    function renderInspector() {
        const el = $('[data-inspector]');
        const p = S.selected && positionByCode(S.selected);
        if (!p) { el.innerHTML = ''; return; }
        const role = roles[p.role];
        const a = S.assignments[p.code];
        const m = a && memberById.get(a.member_id);
        el.innerHTML = `<div class="card p-4 ring-2 ring-sun-400/50">
            <div class="flex items-start justify-between"><div><p class="text-[11px] font-bold uppercase tracking-wider muted">Poste sélectionné</p><p class="font-extrabold text-lg">${e(p.label)}</p>
              <p class="text-xs muted">${e(role?.zone)}${p.bwa ? ` · Bwa n°${p.bwa} · ${p.side === 'babord' ? 'Bâbord' : 'Tribord'}` : ''}${p.optional ? ' · optionnel' : ''}</p></div>
              <button type="button" data-action="unselect" class="w-8 h-8 grid place-items-center rounded-lg hover:bg-slate-100" aria-label="Fermer">${icon('x')}</button></div>
            ${m ? `<div class="mt-3 flex items-center gap-3 p-3 rounded-xl bg-slate-50"><span class="w-11 h-11 rounded-full grid place-items-center font-bold text-white" style="background:${e(m.color)}">${e(m.initials)}</span>
                <div class="flex-1 min-w-0"><p class="font-bold truncate">${e(m.name)}</p><p class="text-xs muted">${[m.kg ? kg(m.kg) : null, m.cm ? `${m.cm} cm` : null, m.level].filter(Boolean).map(e).join(' · ')}</p></div></div>
                ${p.role === 'dresseur' ? `<p class="label mt-4">Position sur le bwa</p>${placementControl(p.code)}<p class="text-[11px] muted mt-2">La position des dresseurs s’adapte au vent et à l’équilibre de la yole.</p>` : ''}
                <button type="button" data-action="clear-pos" class="btn-ghost btn-sm w-full mt-4">${icon('trash')}Retirer du poste</button>`
            : '<p class="text-sm muted mt-3">Glissez un membre sur ce poste, ou cliquez sur un membre de la liste. Les membres habitués à ce poste sont surlignés.</p>'}
          </div>`;
    }

    function renderSheet() {
        const el = $('[data-sheet]');
        const p = S.selected && positionByCode(S.selected);
        if (!p || !isMobile()) { el.innerHTML = ''; document.body.classList.remove('overflow-hidden'); return; }
        const role = roles[p.role];
        const a = S.assignments[p.code];
        const current = a && memberById.get(a.member_id);
        const list = filteredMembers();
        const fit = list.filter((m) => m.roles.includes(p.role));
        const others = list.filter((m) => !m.roles.includes(p.role));
        document.body.classList.add('overflow-hidden');
        el.innerHTML = `<div class="lg:hidden fixed inset-0 z-50 bg-navy-950/40" data-action="unselect"></div>
          <div class="lg:hidden fixed inset-x-0 bottom-0 z-[60] bg-white rounded-t-3xl shadow-2xl max-h-[75vh] flex flex-col safe-b" role="dialog" aria-label="${e(p.label)}">
            <div class="pt-2.5 pb-3 px-5 border-b border-slate-100"><div class="w-10 h-1.5 rounded-full bg-slate-200 mx-auto mb-3"></div>
              <div class="flex items-center gap-3"><span class="w-10 h-10 rounded-full grid place-items-center text-white font-bold text-xs" style="background:${e(role?.color)}">${e(role?.short)}</span>
                <div class="flex-1 min-w-0"><p class="font-extrabold">${e(p.label)}</p><p class="text-xs muted truncate">${current ? `Actuellement : ${e(current.name)}${current.kg ? ' · ' + kg(current.kg) : ''}` : 'Poste à pourvoir'}</p></div>
                <button type="button" data-action="unselect" class="w-9 h-9 grid place-items-center rounded-full bg-slate-100" aria-label="Fermer">${icon('x')}</button></div>
              ${p.role === 'dresseur' && current ? `<div class="mt-3">${placementControl(p.code)}</div>` : ''}
            </div>
            <div class="overflow-y-auto p-4 space-y-2">
              <p class="text-[11px] font-bold uppercase tracking-wider muted">Suggérés pour ce poste</p>
              ${fit.map(memberCard).join('') || '<p class="text-sm muted">Aucun membre disponible habitué à ce poste.</p>'}
              <p class="text-[11px] font-bold uppercase tracking-wider muted pt-2">Autres membres</p>
              ${others.map(memberCard).join('') || '<p class="text-sm muted">—</p>'}
            </div>
            ${current ? `<div class="p-4 border-t border-slate-100 grid grid-cols-2 gap-2"><button type="button" data-action="clear-pos" class="btn-ghost">${icon('trash')}Retirer</button><button type="button" data-action="unselect" class="btn-primary">Terminé</button></div>` : ''}
          </div>`;
    }

    function renderStatic() {
        $('[data-legend]').innerHTML = Object.entries(roles).filter(([code]) => code !== 'deuxieme_corde')
            .map(([code, r]) => `<div class="flex items-center gap-2.5 text-sm"><span class="w-3.5 h-3.5 rounded-full" style="background:${e(r.color)}"></span><span class="font-semibold flex-1">${code === 'premiere_corde' ? '1ère / 2ème corde' : e(r.label)}</span><span class="text-[11px] muted">${e(r.zone)}</span></div>`).join('');
        $('[data-wind-dir]').value = S.wind.dir ?? '';
        $('[data-wind-kts]').value = S.wind.kts ?? '';
        const onSiteCount = members.filter(onSite).length;
        $('[data-availability-note]').innerHTML = data.attendanceRecorded
            ? `Seuls les membres pointés présents ou en retard sont proposés. <a class="font-semibold text-navy-700" href="${e(data.attendanceUrl)}">Modifier l’appel</a>`
            : `L’appel n’a pas encore été fait : tous les membres actifs sont proposés. <a class="font-semibold text-navy-700" href="${e(data.attendanceUrl)}">Faire l’appel</a>`;
        $('[data-available-count]').textContent = data.attendanceRecorded ? `${onSiteCount} présents` : `${members.length} membres`;
    }

    function render() {
        const b = stats();
        $('[data-configs]').innerHTML = configurations.map((c) => `<button type="button" data-config="${c.id}" class="${c.id === S.configId ? 'on' : ''}">${e(c.name)}</button>`).join('');
        $('[data-canvas]').innerHTML = yoleSVG({
            config: config(), roles, members: drawingMembers, assignments: S.assignments, selected: S.selected,
            interactive: true, wind: S.wind.dir === null || S.wind.dir === '' ? null : S.wind, boatColor: data.boatColor,
        });
        $$('[data-balance]', root).forEach((el) => { el.innerHTML = balanceCard(b); });
        $('[data-role-filters]').innerHTML = ROLE_FILTERS.map((f) => `<button type="button" data-role-filter="${f}" class="chip whitespace-nowrap cursor-pointer ${S.roleFilter === f ? 'bg-navy-900 text-white' : 'bg-slate-100 text-slate-600'}">${f === 'tous' ? 'Tous' : f === 'premiere_corde' ? 'Cordes' : e(roles[f]?.label)}</button>`).join('');
        $('[data-member-list]').innerHTML = filteredMembers().map(memberCard).join('') || '<p class="text-sm muted">Aucun membre.</p>';
        renderProgress(b);
        renderStatus();
        renderInspector();
        renderSheet();
    }

    // ---------- events ----------
    document.addEventListener('click', async (event) => {
        const t = event.target.closest('[data-pos],[data-member],[data-config],[data-place],[data-role-filter],[data-action]');
        if (!t) return;
        const d = t.dataset;
        if (d.pos) { S.selected = S.selected === d.pos ? null : d.pos; render(); return; }
        if (d.member) {
            const id = +d.member;
            if (memberById.get(id)?.elsewhere) { toast(`Déjà placé sur ${memberById.get(id).elsewhere}`); return; }
            if (S.selected) assign(S.selected, id); else toast('Sélectionnez d’abord un poste sur la yole');
            return;
        }
        if (d.config) { if (+d.config !== S.configId) switchConfiguration(+d.config); return; }
        if (d.place && S.selected && S.assignments[S.selected]) { S.assignments[S.selected].placement = d.place; changed(); return; }
        if (d.roleFilter) { S.roleFilter = d.roleFilter; render(); return; }
        switch (d.action) {
            case 'unselect': if (event.target === t || t.tagName === 'BUTTON') { S.selected = null; render(); } break;
            case 'clear-pos': delete S.assignments[S.selected]; changed(); break;
            case 'reset':
                if (Object.keys(S.assignments).length && confirm('Retirer tous les membres de la yole ?')) { S.assignments = {}; S.selected = null; changed(); }
                break;
            case 'validate': {
                const saved = await saveNow();
                if (!saved) return;
                if (saved === 'offline') { toast('Plan gardé sur l’appareil : la validation sera possible au retour du réseau.', 'sun'); return; }
                const b = stats();
                const modal = document.querySelector('[data-validate-modal]');
                modal.querySelector('[data-validate-summary]').textContent = `${config().name} · ${b.filled}/${b.positions} postes · ${kg(b.total)} à bord. Le plan sera figé pour cette sortie (il reste modifiable en le rouvrant).`;
                modal.querySelector('[data-validate-sides]').textContent = `${kg(b.babord)} / ${kg(b.tribord)}`;
                modal.querySelector('[data-validate-ends]').textContent = `${kg(b.avant)} / ${kg(b.arriere)}`;
                modal.querySelector('[data-validate-submit]').disabled = b.filled === 0;
                modal.classList.replace('hidden', 'grid');
                break;
            }
            case 'close-modal': document.querySelector('[data-validate-modal]').classList.replace('grid', 'hidden'); break;
        }
    });

    root.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && event.target.closest('[data-pos],[data-member]')) {
            event.preventDefault();
            // SVG seats have no .click(): dispatch the event so the delegated handler runs.
            event.target.closest('[data-pos],[data-member]').dispatchEvent(new MouseEvent('click', { bubbles: true }));
        }
        if (event.key === 'Escape' && S.selected) { S.selected = null; render(); }
    });

    root.addEventListener('input', (event) => {
        if (event.target.matches('[data-member-search]')) { S.search = event.target.value; $('[data-member-list]').innerHTML = filteredMembers().map(memberCard).join(''); }
    });
    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-wind-dir]')) { S.wind.dir = event.target.value === '' ? null : +event.target.value; changed(); }
        if (event.target.matches('[data-wind-kts]')) { S.wind.kts = event.target.value === '' ? null : +event.target.value; changed(); }
    });

    // Drag & drop (desktop): members from the list onto seats.
    root.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-member]');
        if (!card) return;
        event.dataTransfer.setData('text/plain', card.dataset.member);
        event.dataTransfer.effectAllowed = 'move';
        card.classList.add('drag-ghost');
    });
    root.addEventListener('dragend', (event) => event.target.closest?.('[data-member]')?.classList.remove('drag-ghost'));
    root.addEventListener('dragover', (event) => {
        const seat = event.target.closest('[data-pos]');
        if (!seat) return;
        event.preventDefault();
        $$('.drop-over', root).forEach((el) => el !== seat && el.classList.remove('drop-over'));
        seat.classList.add('drop-over');
    });
    root.addEventListener('dragleave', (event) => event.target.closest('[data-pos]')?.classList.remove('drop-over'));
    root.addEventListener('drop', (event) => {
        const seat = event.target.closest('[data-pos]');
        if (!seat) return;
        event.preventDefault();
        S.selected = seat.dataset.pos;
        assign(seat.dataset.pos, +event.dataTransfer.getData('text/plain'));
    });

    window.addEventListener('resize', () => renderSheet());

    renderStatic();
    render();
    setSaveState('Enregistré');

    // A version edited offline on this device takes precedence over the (possibly cached) page data.
    pending().then((operations) => {
        const queued = operations.find((op) => op.key === queueKey);
        if (!queued) return;
        const state = queued.payload;
        const configuration = configurations.find((c) => c.id === state.boat_configuration_id);
        if (!configuration) return;
        S.configId = configuration.id;
        S.wind = { dir: state.wind_direction, kts: state.wind_strength };
        S.assignments = Object.fromEntries(state.assignments
            .map((a) => [configuration.positions.find((p) => p.id === a.position_id)?.code, { member_id: a.member_id, placement: a.bwa_placement }])
            .filter(([code]) => code));
        S.offline = true;
        renderStatic();
        render();
        setSaveState('Sur l’appareil');
    }).catch(() => {});
}
