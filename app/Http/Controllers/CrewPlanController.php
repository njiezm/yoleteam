<?php

namespace App\Http\Controllers;

use App\Enums\CrewPlanStatus;
use App\Http\Requests\UpdateCrewPlanRequest;
use App\Models\Boat;
use App\Models\BoatConfiguration;
use App\Models\CrewAssignment;
use App\Models\CrewPlan;
use App\Models\Member;
use App\Models\Outing;
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

    public function edit(Request $request, Outing $outing, CrewPlan $crewPlan, CrewPlanPresenter $presenter): View
    {
        $crewPlan->load(['boat.configurations.positions.crewRole', 'assignments.position']);
        $outing->load(['crewPlans.boat', 'attendances']);

        $statuses = $outing->attendances->pluck('status', 'member_id');
        $assignedHere = $crewPlan->assignments->pluck('member_id');

        // Members already seated on another boat of the same outing.
        $elsewhere = CrewAssignment::query()
            ->whereIn('crew_plan_id', $outing->crewPlans->where('id', '!=', $crewPlan->id)->pluck('id'))
            ->get(['crew_plan_id', 'member_id'])
            ->mapWithKeys(fn (CrewAssignment $assignment) => [
                $assignment->member_id => $outing->crewPlans->firstWhere('id', $assignment->crew_plan_id)->boat->name,
            ]);

        $members = Member::query()
            ->forAssociation($request->user()->association_id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $assignedHere))
            ->with('crewRoles')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $member->full_name,
                ...$presenter->member($member),
                'kg' => $member->weight_kg !== null ? (float) $member->weight_kg : null,
                'cm' => $member->height_cm,
                'level' => $member->level->label(),
                'roles' => $member->orderedCrewRoles()->pluck('code')->all(),
                'status' => $statuses->get($member->id)?->value,
                'elsewhere' => $elsewhere->get($member->id),
            ])
            ->values();

        return view('crew-plans.edit', [
            'outing' => $outing,
            'plan' => $crewPlan,
            'editor' => [
                'plan' => [
                    'uuid' => $crewPlan->uuid,
                    'label' => $crewPlan->boat->name.' · '.$outing->title,
                    'status' => $crewPlan->status->value,
                    'version' => $crewPlan->version,
                    'configuration_id' => $crewPlan->boat_configuration_id,
                    'wind_direction' => $crewPlan->wind_direction ?? $outing->wind_direction,
                    'wind_strength' => $crewPlan->wind_strength ?? $outing->wind_strength,
                    'update_url' => route('crew-plans.update', [$outing, $crewPlan]),
                ],
                'boatColor' => $crewPlan->boat->color(),
                'configurations' => $crewPlan->boat->configurations
                    ->map(fn (BoatConfiguration $configuration) => $presenter->configuration($configuration))
                    ->values(),
                'roles' => $presenter->roles(),
                'members' => $members,
                'assignments' => (object) $presenter->assignments($crewPlan),
                'attendanceRecorded' => $statuses->isNotEmpty(),
                'attendanceUrl' => route('attendance.edit', $outing),
            ],
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
