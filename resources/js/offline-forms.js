// Forms marked [data-offline-form] keep working without network: the submission is stored in the device
// queue and replayed on the server by the sync (same route, same validation and permissions as online).
import { enqueue, uuid } from './offline-queue';
import { toast } from './http';

/** Turns form entries into the nested structure PHP builds from "a[b][]" style names. */
export function formToObject(form, submitter) {
    const data = {};
    const entries = [...new FormData(form, submitter).entries()].filter(([, value]) => typeof value === 'string');

    entries.forEach(([name, value]) => {
        const keys = name.replace(/\]/g, '').split('[');
        let node = data;
        keys.forEach((key, index) => {
            const last = index === keys.length - 1;
            if (key === '') {
                // "name[]": append to a list.
                if (last) { node.push(value); return; }
                const next = {};
                node.push(next);
                node = next;
                return;
            }
            if (last) {
                node[key] = value;
            } else {
                node[key] ??= keys[index + 1] === '' ? [] : {};
                node = node[key];
            }
        });
    });

    return data;
}

export function mountOfflineForms(root = document) {
    root.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-offline-form]');
        if (!form || navigator.onLine) return;
        event.preventDefault();

        const fields = formToObject(form, event.submitter);
        const method = (fields._method || form.method || 'POST').toUpperCase();
        const id = uuid();
        await enqueue({
            key: `form:${id}`,
            entity: 'form',
            entity_uuid: id,
            payload: { method, url: new URL(form.action, location.origin).pathname, fields, label: form.dataset.offlineForm || document.title },
            label: form.dataset.offlineForm || document.title,
        });

        toast('Enregistré sur l’appareil : envoyé au serveur au retour du réseau.', 'sun');
        const next = form.dataset.offlineRedirect;
        setTimeout(() => { if (next) location.href = next; else history.back(); }, 900);
    });
}
