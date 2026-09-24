import { mountDrawings } from './yole';
import { mountAttendance } from './attendance';
import { mountCrewPlanEditor } from './crew-plan-editor';
import { mountSync } from './sync';

document.addEventListener('DOMContentLoaded', () => {
    mountDrawings();
    mountSync();

    const attendance = document.querySelector('[data-attendance]');
    if (attendance) mountAttendance(attendance);

    const editor = document.querySelector('[data-crew-editor]');
    if (editor) mountCrewPlanEditor(editor);

    // Server flash messages fade out on their own.
    const flash = document.querySelector('[data-flash]');
    if (flash) setTimeout(() => flash.remove(), 2800);

    document.querySelectorAll('[data-print]').forEach((btn) => btn.addEventListener('click', () => window.print()));
});
