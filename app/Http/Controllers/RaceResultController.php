<?php

namespace App\Http\Controllers;

use App\Enums\RaceResultStatus;
use App\Http\Requests\UpdateRaceResultsRequest;
use App\Models\Boat;
use App\Models\Race;
use App\Models\RaceResult;
use App\Models\RaceStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RaceResultController extends Controller
{
    /**
     * Upserts one result per boat; rows left empty remove the boat's result.
     */
    public function update(UpdateRaceResultsRequest $request, Race $race, RaceStage $stage): RedirectResponse
    {
        $boatIds = Boat::query()->forAssociation($request->user()->association_id)->pluck('id');
        $rows = collect($request->validated('results'))->only($boatIds->all());

        DB::transaction(function () use ($rows, $stage) {
            foreach ($rows as $boatId => $row) {
                $status = RaceResultStatus::tryFrom((string) ($row['status'] ?? '')) ?? RaceResultStatus::Classe;
                $isEmpty = blank($row['rank'] ?? null) && blank($row['time'] ?? null) && blank($row['points'] ?? null)
                    && blank($row['notes'] ?? null) && $status === RaceResultStatus::Classe;

                if ($isEmpty) {
                    $stage->results()->where('boat_id', $boatId)->delete();

                    continue;
                }

                RaceResult::updateOrCreate(
                    ['race_stage_id' => $stage->id, 'boat_id' => $boatId],
                    [
                        'rank' => $row['rank'] ?? null,
                        'elapsed_seconds' => RaceResult::parseElapsed($row['time'] ?? null),
                        'points' => $row['points'] ?? null,
                        'status' => $status,
                        'notes' => $row['notes'] ?? null,
                    ],
                );
            }
        });

        return redirect()->route('races.show', $race)->with('status', "Résultats de l’étape {$stage->number} enregistrés");
    }
}
