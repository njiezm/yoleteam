<?php

namespace App\Http\Controllers;

use App\Enums\CrewPlanStatus;
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

    public function store(Request $request, Outing $outing): RedirectResponse
    {
        $data = $request->validate([
            'boat_id' => [
                'required', 'integer',
                Rule::exists('boats', 'id')->where('association_id', $request->user()->association_id)->where('is_active', true),
                Rule::unique('crew_plans', 'boat_id')->where('outing_id', $outing->id)->whereNull('deleted_at'),
            ],
            'boat_configuration_id' => ['nullable', 'integer'],
        ], [
            'boat_id.unique' => 'Cette yole a déjà un plan d’équipage pour cette sortie.',
        ]);

        $boat = Boat::query()->with('configurations')->findOrFail($data['boat_id']);
        $configuration = $boat->configurations->firstWhere('id', (int) ($data['boat_configuration_id'] ?? 0))
            ?? $boat->configurations->firstWhere('is_default', true)
            ?? $boat->configurations->firstOrFail();

        // A soft-deleted plan for the same boat still holds the (outing, boat) unique key: bring it back.
        $plan = CrewPlan::withTrashed()->firstOrNew(['outing_id' => $outing->id, 'boat_id' => $boat->id]);
        $plan->forceFill([
            'boat_configuration_id' => $configuration->id,
            'status' => CrewPlanStatus::Brouillon,
            'validated_at' => null,
            'validated_by' => null,
            'created_by' => $plan->created_by ?? $request->user()->id,
            'deleted_at' => null,
        ])->save();

        return redirect()->route('crew-plans.edit', [$outing, $plan]);
    }

    public function show(Outing $outing, CrewPlan $crewPlan, CrewPlanPresenter $presenter): View
    {
        $crewPlan->load(['boat', 'configuration.positions.crewRole', 'validator', 'assignments.position.crewRole', 'assignments.member.crewRoles']);

        return view('crew-plans.show', [
            'outing' => $outing,
            'plan' => $crewPlan,
            'drawing' => $presenter->drawing($crewPlan->boat, $crewPlan->configuration, $crewPlan),
            'balance' => $presenter->balance($crewPlan),
            'groups' => $presenter->groupedAssignments($crewPlan),
        ]);
    }

    public function edit(Request $request, Outing $outing, CrewPlan $crewPlan, CrewPlanEditorData $editorData): View
    {
        $crewPlan->load(['boat', 'configuration', 'assignments.position']);

        return view('crew-plans.edit', [
            'outing' => $outing->load(['crewPlans.boat', 'attendances']),
            'plan' => $crewPlan,
            'editor' => $editorData->build($outing, $crewPlan->boat, $crewPlan, $request->user()->association_id),
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
