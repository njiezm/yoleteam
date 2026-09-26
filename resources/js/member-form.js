// Member form: shows the age computed from the birth date next to the field, updated as it is typed.

/** Full years between a "YYYY-MM-DD" date and today, or null when the date is empty, invalid or in the future. */
export function ageFrom(value, today = new Date()) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    if (!match) return null;

    const [year, month, day] = match.slice(1).map(Number);
    let age = today.getFullYear() - year;
    if (today.getMonth() + 1 < month || (today.getMonth() + 1 === month && today.getDate() < day)) age--;

    return age >= 0 && age < 130 ? age : null;
}

export function mountMemberForm(root) {
    const birthDate = root.querySelector('[data-member-birth-date]');
    const output = root.querySelector('[data-member-age]');
    if (!birthDate || !output) return;

    const render = () => {
        const age = ageFrom(birthDate.value);
        output.textContent = age === null ? '' : `${age} an${age > 1 ? 's' : ''}`;
    };

    birthDate.addEventListener('input', render);
    birthDate.addEventListener('change', render);
    render();
}
