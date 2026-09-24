import { mountDrawings } from './yole';
import { mountAttendance } from './attendance';
import { mountCrewPlanEditor } from './crew-plan-editor';
import { mountSync } from './sync';
import { mountOfflineForms } from './offline-forms';
import { mountOfflineOuting } from './offline-outing';

document.addEventListener('DOMContentLoaded', () => {
    mountDrawings();
    mountSync();
    mountOfflineForms();

    const outingPlans = document.querySelector('[data-outing-plans]');
    if (outingPlans) mountOfflineOuting(outingPlans);

    const attendance = document.querySelector('[data-attendance]');
    if (attendance) mountAttendance(attendance);

    const editor = document.querySelector('[data-crew-editor]');
    if (editor) mountCrewPlanEditor(editor);

    // Server flash messages fade out on their own.
    const flash = document.querySelector('[data-flash]');
    if (flash) setTimeout(() => flash.remove(), 2800);

    document.querySelectorAll('[data-print]').forEach((btn) => btn.addEventListener('click', () => window.print()));
});
