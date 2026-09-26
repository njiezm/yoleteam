// Double submissions (double tap, slow network) used to create members or outings twice.
// 1. A form that is being sent ignores any further submit and its buttons are disabled.
// 2. Creation forms carry a uuid generated on the device ([data-fresh-uuid]): the server treats a second
//    request with the same uuid as the first one (it opens the record instead of creating another).

const freshUuid = () => (crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)));

function refreshUuids() {
    // A page served from the offline cache (or the back/forward cache) must not reuse an old uuid.
    document.querySelectorAll('input[data-fresh-uuid]').forEach((input) => { input.value = freshUuid(); });
}

function release(form) {
    delete form.dataset.submitting;
    [...form.elements].filter((el) => el.hasAttribute('data-was-enabled')).forEach((button) => {
        button.disabled = false;
        button.removeAttribute('data-was-enabled');
    });
}

export function mountSubmitOnce() {
    refreshUuids();

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || (form.method || 'get').toLowerCase() === 'get') return;
        if (form.dataset.submitting) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    // Bubble phase on window: runs after the offline handler, which cancels the navigation when offline.
    window.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented || (form.method || 'get').toLowerCase() === 'get') return;
        form.dataset.submitting = '1';
        // After the form data is built (a disabled submitter would be left out of it).
        setTimeout(() => {
            // form.elements also holds the buttons placed outside the form (form="…", e.g. in the page header).
            [...form.elements].filter((el) => el.type === 'submit').forEach((button) => {
                if (!button.disabled) {
                    button.disabled = true;
                    button.setAttribute('data-was-enabled', '');
                }
            });
        }, 0);
        // Downloads or a navigation that never happens (server error): allow a new try after a while.
        setTimeout(() => release(form), 8000);
    });

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        document.querySelectorAll('form[data-submitting]').forEach(release);
        refreshUuids();
    });
}
