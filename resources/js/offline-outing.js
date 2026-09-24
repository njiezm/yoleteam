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
    const form = document.querySelector('[data-plan-create]');
    const host = document.querySelector('[data-offline-editor-host]');
    const template = document.querySelector('[data-offline-editor]');

    const open = async (boatId, planUuid) => {
        if (!templates[boatId] || !host || !template) {
            toast('Cette yole ne peut pas être préparée hors ligne : rouvrez la sortie avec du réseau.', 'error');
            return;
        }
        const data = structuredClone(templates[boatId]);
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

    form?.addEventListener('submit', (event) => {
        if (navigator.onLine) return;
        event.preventDefault();
        open(form.querySelector('[name="boat_id"]').value, uuid());
    });

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-offline-plan]');
        if (button) open(button.dataset.boat, button.dataset.offlinePlan);
    });

    // Plans created offline for this outing that are still waiting for the network.
    pending().then((operations) => {
        const mine = operations.filter((op) => op.entity === 'crew_plan' && op.payload?.create?.outing_uuid === outingUuid);
        const started = new Set(mine.map((op) => String(op.payload.create.boat_id)));
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
            const name = templates[boatId]?.plan.label.split(' · ')[0] ?? 'Yole';
            form?.querySelector(`[name="boat_id"] option[value="${boatId}"]`)?.remove();
            return `<div class="card p-4 flex flex-wrap items-center gap-3 bg-amber-50 border-amber-200">
                <span class="w-10 h-10 rounded-xl bg-amber-400 text-navy-950 grid place-items-center font-extrabold">${op.payload.assignments.length}</span>
                <div class="flex-1 min-w-0"><p class="font-bold">${e(name)} · plan créé hors ligne</p>
                  <p class="text-xs text-amber-800">${op.payload.assignments.length} poste(s) pourvu(s)${op.payload.validate ? ' · validation demandée' : ''} — envoyé au retour du réseau</p></div>
                <button type="button" class="btn-primary btn-sm" data-offline-plan="${e(op.entity_uuid)}" data-boat="${e(boatId)}">Continuer</button>
              </div>`;
        }).join('');
        if (form && !form.querySelector('[name="boat_id"] option')) form.classList.add('hidden');
    }).catch(() => {});
}
