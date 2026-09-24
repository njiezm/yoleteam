// Small fetch helpers shared by the interactive pages.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

export function setCsrf(token) {
    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
    document.querySelectorAll('input[name="_token"]').forEach((input) => { input.value = token; });
}

/** Asks the server for the session's current CSRF token (pages served from the offline cache may hold an old one). */
export async function refreshCsrf() {
    const response = await fetch(document.body.dataset.syncTokenUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) {
        const error = new Error(response.status === 401 ? 'Session expirée : reconnectez-vous pour synchroniser.' : `Erreur ${response.status}`);
        error.status = response.status;
        throw error;
    }
    const { token } = await response.json();
    setCsrf(token);
    return token;
}

function offlineError() {
    const error = new Error('Pas de réseau');
    error.offline = true;
    return error;
}

/**
 * JSON request with CSRF token. Rejects with `error.offline = true` when the network is unreachable,
 * or with a readable message on HTTP errors. A 419 (stale token) is retried once with a fresh token.
 */
export async function send(url, method, payload, retried = false) {
    if (!navigator.onLine) throw offlineError();

    let response;
    try {
        response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        });
    } catch {
        throw offlineError();
    }

    if (response.status === 419 && !retried) {
        await refreshCsrf();
        return send(url, method, payload, true);
    }

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        const error = new Error(first || data.message || `Erreur ${response.status}`);
        error.status = response.status;
        throw error;
    }
    return data;
}

let toastTimer;

/** Bottom toast, same look as the server-side flash message. */
export function toast(message, tone = 'navy') {
    let host = document.getElementById('toast');
    if (!host) {
        host = document.createElement('div');
        host.id = 'toast';
        host.className = 'no-print fixed z-[80] left-1/2 -translate-x-1/2 bottom-24 lg:bottom-8 pointer-events-none';
        document.body.append(host);
    }
    const tones = { navy: 'bg-navy-900 text-white', error: 'bg-red-600 text-white', sun: 'bg-amber-400 text-navy-950', green: 'bg-emerald-600 text-white' };
    host.innerHTML = '';
    const el = document.createElement('div');
    el.className = `rounded-xl ${tones[tone] || tones.navy} px-4 py-3 shadow-2xl text-sm font-semibold`;
    el.textContent = message;
    host.append(el);
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { host.innerHTML = ''; }, 3200);
}
