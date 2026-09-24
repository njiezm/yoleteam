<?php

namespace App\Http\Controllers;

use App\Models\Race;
use App\Models\RaceStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The race page holds one form per stage: each validates into its own error bag.
 */
class RaceStageController extends Controller
{
    public function store(Request $request, Race $race): RedirectResponse
    {
        $race->stages()->create($request->validateWithBag('newStage', $this->rules($race)));

        return redirect()->route('races.show', $race)->with('status', 'Étape ajoutée');
    }

    public function update(Request $request, Race $race, RaceStage $stage): RedirectResponse
    {
        $stage->update($request->validateWithBag('stage-'.$stage->id, $this->rules($race, $stage)));

        return redirect()->route('races.show', $race)->with('status', 'Étape enregistrée');
    }

    public function destroy(Race $race, RaceStage $stage): RedirectResponse
    {
        $stage->delete();

        return redirect()->route('races.show', $race)->with('status', 'Étape supprimée');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Race $race, ?RaceStage $stage = null): array
    {
        return [
            'number' => [
                'required', 'integer', 'between:1,99',
                Rule::unique('race_stages', 'number')->where('race_id', $race->id)->ignore($stage),
            ],
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_location' => ['nullable', 'string', 'max:255'],
            'end_location' => ['nullable', 'string', 'max:255'],
            'distance_nm' => ['nullable', 'numeric', 'between:0,999'],
        ];
    }
}
