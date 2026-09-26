// Password fields (<x-password-input>): the eye button switches the input between hidden and visible text.

export function mountPasswordToggles(root = document) {
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) return;

        const wrapper = button.closest('[data-password-field]');
        const input = wrapper?.querySelector('input');
        if (!input) return;

        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';

        const label = reveal ? button.dataset.labelHide : button.dataset.labelShow;
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-pressed', String(reveal));
        button.title = label;
        wrapper.querySelector('[data-password-icon="show"]')?.classList.toggle('hidden', reveal);
        wrapper.querySelector('[data-password-icon="hide"]')?.classList.toggle('hidden', !reveal);
        input.focus();
    });
}
