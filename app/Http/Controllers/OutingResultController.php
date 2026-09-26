<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOutingResultsRequest;
use App\Models\Outing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OutingResultController extends Controller
{
    /**
     * Replaces every race of the outing (numbered in the submitted order) and saves its rankings.
     */
    public function update(UpdateOutingResultsRequest $request, Outing $outing): RedirectResponse
    {
        $races = $request->races();

        DB::transaction(function () use ($request, $outing, $races) {
            $outing->races()->delete();

            foreach ($races as $index => $race) {
                $outing->races()->create([...$race, 'number' => $index + 1]);
            }

            $outing->update($request->rankings());
        });

        return redirect()->to(route('outings.show', $outing).'#resultats')->with('status', 'Résultats enregistrés');
    }
}
