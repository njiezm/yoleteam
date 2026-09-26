import { mountDrawings } from './yole';
import { mountAttendance } from './attendance';
import { mountCrewPlanEditor } from './crew-plan-editor';
import { mountSync } from './sync';
import { mountMemberForm } from './member-form';
import { mountPasswordToggles } from './password-toggle';
import { mountOfflineForms } from './offline-forms';
import { mountOfflineOuting } from './offline-outing';
import { mountOfflineOutingList, mountOfflineOutingPage, mountOfflineOutingPickers, mountOfflineShortcuts } from './offline-outing-page';

document.addEventListener('DOMContentLoaded', () => {
    mountDrawings();
    mountSync();
    mountOfflineForms();
    mountOfflineShortcuts();
    mountOfflineOutingPickers();
    mountPasswordToggles();

    const memberForm = document.querySelector('[data-member-form]');
    if (memberForm) mountMemberForm(memberForm);

    // Pages of outings created offline mount their widgets themselves ([data-deferred]).
    const offlineOuting = document.querySelector('[data-offline-outing-page]');
    if (offlineOuting) mountOfflineOutingPage(offlineOuting);

    const outingPlans = document.querySelector('[data-outing-plans]:not([data-deferred])');
    if (outingPlans) mountOfflineOuting(outingPlans);

    const offlineOutings = document.querySelector('[data-offline-outings]');
    if (offlineOutings) mountOfflineOutingList(offlineOutings);

    const attendance = document.querySelector('[data-attendance]:not([data-deferred])');
    if (attendance) mountAttendance(attendance);

    const editor = document.querySelector('[data-crew-editor]');
    if (editor) mountCrewPlanEditor(editor);

    // Server flash messages fade out on their own.
    const flash = document.querySelector('[data-flash]');
    if (flash) setTimeout(() => flash.remove(), 2800);

    document.querySelectorAll('[data-print]').forEach((btn) => btn.addEventListener('click', () => window.print()));
});
