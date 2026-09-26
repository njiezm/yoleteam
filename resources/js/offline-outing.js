// Outing page offline: "Créer le plan" opens the crew plan editor in place (the plan gets a client uuid and
// is created on the server when the queue is synced); plans created offline are listed so they can be resumed.
import { mountCrewPlanEditor } from './crew-plan-editor';
import { pending, uuid } from './offline-queue';
import { escapeHtml as e } from './yole';
import { toast } from './http';

/**
 * @param {HTMLElement} root [data-outing-plans]
 * @param {{title?: string, wind?: {dir: ?number, kts: ?number}, engagedBoats?: Array<string|number>, attendanceUrl?: string}} context
 *        for outings created offline (the server does not know them yet)
 */
export function mountOfflineOuting(root, context = {}) {
    const templates = JSON.parse(document.querySelector('[data-plan-templates]')?.textContent || '{}');
    const editorMembers = JSON.parse(document.querySelector('[data-editor-members]')?.textContent || '[]');
    const outingUuid = root.dataset.outingUuid;
    const form = root.querySelector('[data-plan-create]:not([data-template])');
    const host = document.querySelector('[data-offline-editor-host]');
    const template = document.querySelector('[data-offline-editor]');

    /** @param {string} key boat id, or "boatId:race" for the next race of a championship day */
    const open = async (key, planUuid) => {
        if (!templates[key] || !host || !template) {
            toast('Cette yole ne peut pas être préparée hors ligne : rouvrez la sortie avec du réseau.', 'error');
            return;
        }
        const data = structuredClone(templates[key]);
        data.plan.uuid = planUuid;
        data.plan.create = { ...data.plan.create, outing_uuid: outingUuid };
        if (context.title) data.plan.label = `${data.plan.label.split(' · ')[0]} · ${context.title}`;
        if (context.wind) {
            data.plan.wind_direction ??= context.wind.dir;
            data.plan.wind_strength ??= context.wind.kts;
        }
        if (!data.members.length) data.members = structuredClone(editorMembers);
        if (context.attendanceUrl) data.attendanceUrl = context.attendanceUrl;

        // The appel recorded offline for this outing decides who is proposed in the editor.
        const queuedStatuses = (await pending().catch(() => []))
            .filter((op) => op.entity === 'attendance' && op.entity_uuid === outingUuid);
        queuedStatuses.forEach((op) => {
            const member = data.members.find((m) => m.id === op.payload.member_id);
            if (member) member.status = op.payload.status;
        });
        data.attendanceRecorded = data.attendanceRecorded || data.members.some((m) => m.status);

        // Show the editor alone in the page.
        [...host.parentElement.children].forEach((el) => { if (el !== host) el.classList.add('hidden'); });
        host.innerHTML = '';
        host.append(template.content.cloneNode(true));
        host.classList.remove('hidden');
        host.querySelector('[data-inline-title]').textContent = `${data.plan.label} — créé hors ligne, envoyé au retour du réseau`;
        mountCrewPlanEditor(host.querySelector('[data-crew-editor]'), data);
        window.scrollTo(0, 0);
    };

    // Offline, "Créer le plan" / "Préparer la manche N" open the editor here instead of calling the server.
    root.addEventListener('submit', (event) => {
        const planForm = event.target.closest('[data-plan-create]');
        if (!planForm || navigator.onLine) return;
        event.preventDefault();
        event.stopPropagation();
        open(planForm.dataset.template || planForm.querySelector('[name="boat_id"]').value, uuid());
    });

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-offline-plan]');
        if (button) open(button.dataset.boat, button.dataset.offlinePlan);
    });

    const keyOf = (create) => ((create.race_number ?? 1) > 1 ? `${create.boat_id}:${create.race_number}` : String(create.boat_id));

    // Plans created offline for this outing that are still waiting for the network.
    pending().then((operations) => {
        const mine = operations.filter((op) => op.entity === 'crew_plan' && op.payload?.create?.outing_uuid === outingUuid);
        const started = new Set(mine.map((op) => keyOf(op.payload.create)));
        // "Préparer la manche N" already started on this device: the plan is listed below instead.
        root.querySelectorAll('[data-plan-create][data-template]').forEach((planForm) => {
            if (started.has(planForm.dataset.template)) planForm.classList.add('hidden');
        });
        // Boats ticked when the outing was created offline, not composed yet.
        const engaged = (context.engagedBoats ?? []).map(String).filter((id) => templates[id] && !started.has(id));
        if (!mine.length && !engaged.length) return;
        const list = root.querySelector('[data-offline-plans]');
        list.classList.remove('hidden');
        list.innerHTML = engaged.map((boatId) => `<div class="card p-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-0"><p class="font-bold">${e(templates[boatId].plan.label.split(' · ')[0])}</p><p class="text-xs muted">Yole engagée · équipage à composer</p></div>
                <button type="button" class="btn-primary btn-sm" data-offline-plan="${e(uuid())}" data-boat="${e(boatId)}">Composer l’équipage</button>
              </div>`).join('') + mine.map((op) => {
            const boatId = op.payload.create.boat_id;
            const key = keyOf(op.payload.create);
            const race = (op.payload.create.race_number ?? 1) > 1 ? ` · manche ${op.payload.create.race_number}` : '';
            const name = (templates[key] ?? templates[boatId])?.plan.label.split(' · ')[0] ?? 'Yole';
            if (!race) form?.querySelector(`[name="boat_id"] option[value="${boatId}"]`)?.remove();
            return `<div class="card p-4 flex flex-wrap items-center gap-3 bg-amber-50 border-amber-200">
                <span class="w-10 h-10 rounded-xl bg-amber-400 text-navy-950 grid place-items-center font-extrabold">${op.payload.assignments.length}</span>
                <div class="flex-1 min-w-0"><p class="font-bold">${e(name)}${e(race)} · plan créé hors ligne</p>
                  <p class="text-xs text-amber-800">${op.payload.assignments.length} poste(s) pourvu(s)${op.payload.validate ? ' · validation demandée' : ''} — envoyé au retour du réseau</p></div>
                <button type="button" class="btn-primary btn-sm" data-offline-plan="${e(op.entity_uuid)}" data-boat="${e(templates[key] ? key : boatId)}">Continuer</button>
              </div>`;
        }).join('');
        if (form && !form.querySelector('[name="boat_id"] option')) form.classList.add('hidden');
    }).catch(() => {});
}
