<?php

namespace App\Http\Controllers;

use App\Enums\OutingStatus;
use App\Models\Outing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Last steps of an outing: navigation impressions (end time, distance, one free text) and "Valider la sortie".
 * Outings are addressed by uuid so that the forms also work on outings created offline (replayed after them).
 */
class OutingNavigationController extends Controller
{
    public function update(Request $request, string $uuid): RedirectResponse
    {
        $outing = $this->outing($request, $uuid);

        $data = $request->validate([
            'end_time' => ['nullable', 'date_format:H:i'],
            'distance_nm' => ['nullable', 'numeric', 'between:0,9999.9'],
            'impressions' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'end_time' => 'heure de fin',
            'distance_nm' => 'distance parcourue',
            'impressions' => 'impressions de navigation',
        ]);

        $start = $outing->start_time ? substr((string) $outing->start_time, 0, 5) : null;
        if (filled($data['end_time'] ?? null) && $start && $data['end_time'] <= $start) {
            return back()->withErrors(['end_time' => "L’heure de fin doit être après l’heure de début ($start)."])->withInput();
        }

        $outing->update($data);

        return redirect($this->backUrl($request, $outing))->with('status', 'Impressions de navigation enregistrées');
    }

    public function complete(Request $request, string $uuid): RedirectResponse
    {
        $outing = $this->outing($request, $uuid);
        $outing->update(['status' => OutingStatus::Terminee]);

        return redirect()->route('outings.show', $outing)->with('status', 'Sortie validée — elle reste modifiable');
    }

    public function reopen(Request $request, string $uuid): RedirectResponse
    {
        $outing = $this->outing($request, $uuid);
        $outing->update(['status' => $outing->date->isFuture() ? OutingStatus::Planifiee : OutingStatus::EnCours]);

        return redirect()->route('outings.show', $outing)->with('status', 'Sortie rouverte');
    }

    private function outing(Request $request, string $uuid): Outing
    {
        return Outing::query()->forAssociation($request->user()->association_id)->where('uuid', $uuid)->firstOrFail();
    }

    /** Back to the page the form was on (outing or validated crew plan), never to another site. */
    private function backUrl(Request $request, Outing $outing): string
    {
        $to = (string) $request->input('redirect_to', '');

        return str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : route('outings.show', $outing).'#navigation';
    }
}
