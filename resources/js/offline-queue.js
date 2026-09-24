// Operations recorded on this device while offline (IndexedDB), replayed by sync.js.
// One record per "thing" (a member's status for an outing, a crew plan): a newer change replaces the queued one.

const DB_NAME = 'yoleteam';
const STORE = 'operations';

/** RFC 4122 v4 uuid; crypto.randomUUID() only exists on HTTPS / localhost. */
export function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

let dbPromise;
function db() {
    dbPromise ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE, { keyPath: 'key' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    return dbPromise;
}

async function run(mode, work) {
    const database = await db();
    return new Promise((resolve, reject) => {
        const tx = database.transaction(STORE, mode);
        const request = work(tx.objectStore(STORE));
        tx.oncomplete = () => resolve(request?.result);
        tx.onerror = () => reject(tx.error);
    });
}

const notify = () => window.dispatchEvent(new CustomEvent('yt:queue'));
const currentUser = () => document.body.dataset.userId || null;

/**
 * @param {{key:string, entity:'attendance'|'crew_plan', entity_uuid:string, payload:object, label:string, url?:string}} operation
 */
export async function enqueue(operation) {
    await run('readwrite', (store) => store.put({
        ...operation,
        id: uuid(),
        user_id: currentUser(),
        url: operation.url ?? location.pathname,
        client_updated_at: new Date().toISOString(),
    }));
    notify();
}

/** Queued operations of the signed-in user, oldest first. */
export async function pending() {
    const all = (await run('readonly', (store) => store.getAll())) ?? [];
    return all
        .filter((op) => !op.user_id || op.user_id === currentUser())
        .sort((a, b) => a.client_updated_at.localeCompare(b.client_updated_at));
}

/** Drops the queued change for a key (e.g. after the same thing was saved online). */
export async function forget(key) {
    await run('readwrite', (store) => store.delete(key));
    notify();
}

/** Removes synced operations, unless a newer change replaced them meanwhile. */
export async function acknowledge(ids) {
    const done = new Set(ids);
    const all = (await run('readonly', (store) => store.getAll())) ?? [];
    const keys = all.filter((op) => done.has(op.id)).map((op) => op.key);
    await run('readwrite', (store) => { keys.forEach((key) => store.delete(key)); });
    notify();
}
