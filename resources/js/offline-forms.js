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
        // [data-offline-always]: forms of an outing still on the device, which the server does not know yet.
        if (!form || (navigator.onLine && !form.hasAttribute('data-offline-always'))) return;
        event.preventDefault();
        // Double tap: the second submit is stopped by submit-once.js (capture phase).
        form.dataset.submitting = '1';

        const fields = formToObject(form, event.submitter);
        const method = (fields._method || form.method || 'POST').toUpperCase();
        // Creation forms: the uuid generated on the page identifies the record (the same key twice = one record).
        const id = form.querySelector('input[data-fresh-uuid]')?.value || uuid();
        if (form.hasAttribute('data-offline-uuid')) fields.uuid = id;
        await enqueue({
            key: `form:${id}`,
            entity: 'form',
            entity_uuid: id,
            payload: { method, url: new URL(form.action, location.origin).pathname, fields, label: form.dataset.offlineForm || document.title },
            label: form.dataset.offlineForm || document.title,
        });

        toast('Enregistré sur l’appareil : envoyé au serveur au retour du réseau.', 'sun');
        const next = form.dataset.offlineRedirect?.replace('{uuid}', id);
        setTimeout(() => { if (next) location.href = next; else history.back(); }, 900);
    });
}
