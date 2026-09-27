<?php

namespace App\Http\Controllers;

use App\Enums\BoatSide;
use App\Enums\CrewPlanStatus;
use App\Enums\OutingType;
use App\Enums\RaceOutcome;
use App\Http\Requests\UpdateCrewPlanRequest;
use App\Models\Boat;
use App\Models\CrewPlan;
use App\Models\Outing;
use App\Services\CrewPlanEditorData;
use App\Services\CrewPlanPresenter;
use App\Services\CrewPlanSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrewPlanController extends Controller
{
    /** Shortcut from the navigation: the crew plan(s) of the current outing. */
    public function today(Request $request): RedirectResponse
    {
        $outing = Outing::current($request->user()->association_id)?->load('crewPlans');

        if (! $outing) {
            return redirect()->route('outings.create')->with('status', 'Créez d’abord une sortie pour composer un équipage');
        }

        $plans = $outing->crewPlans;

        return $plans->count() === 1
            ? redirect()->route('crew-plans.edit', [$outing, $plans->first()])
            : redirect()->route('outings.show', $outing);
    }

    /**
     * Engages a boat, or — on a championship day — prepares the crew of the next race, starting from the crew
     * of the previous one (`copy_from`). Submitting twice opens the plan created the first time.
     */
    public function store(Request $request, Outing $outing): RedirectResponse
    {
        $data = $request->validate([
            'boat_id' => [
                'required', 'integer',
                Rule::exists('boats', 'id')->where('association_id', $request->user()->association_id)->where('is_active', true),
            ],
            'race_number' => ['nullable', 'integer', 'min:1', 'max:'.RaceOutcome::MAX_PLACE],
            'copy_from' => ['nullable', 'integer'],
            'boat_configuration_id' => ['nullable', 'integer'],
        ]);

        $raceNumber = $outing->type === OutingType::Regate ? (int) ($data['race_number'] ?? 1) : 1;
        $existing = $outing->crewPlans()->where('boat_id', $data['boat_id'])->where('race_number', $raceNumber)->first();
        if ($existing) {
            return redirect()->route('crew-plans.edit', [$outing, $existing]);
        }

        $source = isset($data['copy_from'])
            ? $outing->crewPlans()->where('boat_id', $data['boat_id'])->find($data['copy_from'])
            : null;

        $boat = Boat::query()->with('configurations')->findOrFail($data['boat_id']);
        $configuration = $boat->configurations->firstWhere('id', (int) ($data['boat_configuration_id'] ?? $source?->boat_configuration_id ?? 0))
            ?? $boat->configurations->firstWhere('is_default', true)
            ?? $boat->configurations->firstOrFail();

        $plan = DB::transaction(function () use ($outing, $boat, $raceNumber, $configuration, $source, $request) {
            // A soft-deleted plan still holds the (outing, boat, race) unique key: bring it back, empty.
            $plan = CrewPlan::withTrashed()->firstOrNew(['outing_id' => $outing->id, 'boat_id' => $boat->id, 'race_number' => $raceNumber]);
            if ($plan->exists) {
                $plan->assignments()->forceDelete();
            }
            $plan->forceFill([
                'boat_configuration_id' => $configuration->id,
                'wind_direction' => $source?->wind_direction,
                'wind_strength' => $source?->wind_strength,
                'bwa_side' => $source?->bwa_side ?? $plan->bwa_side ?? BoatSide::Babord,
                'fond_count' => $source?->fond_count ?? $plan->fond_count ?? 1,
                'status' => CrewPlanStatus::Brouillon,
                'validated_at' => null,
                'validated_by' => null,
                'created_by' => $plan->created_by ?? $request->user()->id,
                'deleted_at' => null,
            ])->save();

            if ($source) {
                $plan->copyCrewFrom($source);
            }

            return $plan;
        });

        return redirect()->route('crew-plans.edit', [$outing, $plan])
            ->with('status', $source ? "Équipage de la manche {$source->race_number} repris : ajustez-le pour la manche {$raceNumber}" : null);
    }

    public function show(Outing $outing, CrewPlan $crewPlan, CrewPlanPresenter $presenter): View
    {
        $crewPlan->load(['boat', 'outing', 'configuration.positions.crewRole', 'validator', 'assignments.position.crewRole', 'assignments.member.crewRoles']);

        return view('crew-plans.show', [
            'outing' => $outing,
            'plan' => $crewPlan,
            'drawing' => $presenter->drawing($crewPlan->boat, $crewPlan->configuration, $crewPlan),
            'balance' => $presenter->balance($crewPlan),
            'groups' => $presenter->groupedAssignments($crewPlan),
        ]);
    }

    /** All the crew plans of the outing side by side (read-only, printable), grouped by race. */
    public function index(Outing $outing, CrewPlanPresenter $presenter): View
    {
        $outing->load(['crewPlans.boat', 'crewPlans.configuration.positions.crewRole', 'crewPlans.assignments.position.crewRole', 'crewPlans.assignments.member.crewRoles']);

        return view('crew-plans.index', [
            'outing' => $outing,
            'presenter' => $presenter,
        ]);
    }

    /**
     * Editor of a plan, with the other boats of the same race on the same page (tabs): the crews of several
     * boats are composed together, with a shared pool of rowers.
     */
    public function edit(Request $request, Outing $outing, CrewPlan $crewPlan, CrewPlanEditorData $editorData): View
    {
        $outing->load(['crewPlans.boat', 'crewPlans.configuration.positions', 'crewPlans.assignments.position', 'attendances']);
        $plans = $outing->crewPlans->where('race_number', $crewPlan->race_number)->values();
        $associationId = $request->user()->association_id;

        return view('crew-plans.edit', [
            'outing' => $outing,
            'plan' => $plans->firstWhere('id', $crewPlan->id),
            'plans' => $plans,
            'editors' => $plans->mapWithKeys(fn (CrewPlan $plan) => [$plan->id => $editorData->build($outing, $plan->boat, $plan, $associationId)]),
            'windOptions' => CrewPlanPresenter::windOptions(),
        ]);
    }

    public function update(UpdateCrewPlanRequest $request, Outing $outing, CrewPlan $crewPlan, CrewPlanSync $sync): JsonResponse
    {
        $plan = $sync->sync($crewPlan, $request->validated());

        return response()->json([
            'status' => $plan->status->value,
            'version' => $plan->version,
            'updated_at' => $plan->updated_at->toIso8601String(),
        ]);
    }

    public function destroy(Outing $outing, CrewPlan $crewPlan): RedirectResponse
    {
        $crewPlan->assignments()->delete();
        $crewPlan->delete();

        return redirect()->route('outings.show', $outing)->with('status', 'Plan d’équipage supprimé');
    }
}
