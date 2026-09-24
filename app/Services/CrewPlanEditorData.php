<?php

namespace App\Services;

use App\Models\Boat;
use App\Models\BoatConfiguration;
use App\Models\CrewAssignment;
use App\Models\CrewPlan;
use App\Models\Member;
use App\Models\Outing;
use Illuminate\Support\Collection;

/**
 * Data consumed by the crew plan editor (resources/js/crew-plan-editor.js), for an existing plan or for a
 * plan that does not exist yet (created offline on the outing page, then replayed by OfflineSync).
 */
class CrewPlanEditorData
{
    public function __construct(private readonly CrewPlanPresenter $presenter) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Outing $outing, Boat $boat, ?CrewPlan $plan, int $associationId): array
    {
        $boat->loadMissing('configurations.positions.crewRole');
        $outing->loadMissing(['crewPlans.boat', 'attendances']);
        $plan?->loadMissing('assignments.position');

        $configuration = $plan?->configuration
            ?? $boat->configurations->firstWhere('is_default', true)
            ?? $boat->configurations->first();

        return [
            'plan' => [
                'uuid' => $plan?->uuid,
                'label' => $boat->name.' · '.$outing->title,
                'status' => $plan?->status->value ?? 'brouillon',
                'version' => $plan?->version ?? 1,
                'configuration_id' => $plan?->boat_configuration_id ?? $configuration?->id,
                'wind_direction' => $plan?->wind_direction ?? $outing->wind_direction,
                'wind_strength' => $plan?->wind_strength ?? $outing->wind_strength,
                'bwa_side' => $plan?->bwa_side?->value ?? 'babord',
                'fond_count' => $plan?->fond_count ?? 1,
                'max_fonds' => BoatLayoutGenerator::MAX_FONDS,
                'update_url' => $plan ? route('crew-plans.update', [$outing, $plan]) : null,
                // Plans created offline carry what the server needs to create them on sync.
                'create' => $plan ? null : ['outing_uuid' => $outing->uuid, 'boat_id' => $boat->id],
            ],
            'boatColor' => $boat->color(),
            'configurations' => $boat->configurations
                ->map(fn (BoatConfiguration $configuration) => $this->presenter->configuration($configuration))
                ->values(),
            'roles' => $this->presenter->roles(),
            'members' => $this->members($outing, $plan, $associationId),
            'assignments' => (object) ($plan ? $this->presenter->assignments($plan) : []),
            'attendanceRecorded' => $outing->attendances->isNotEmpty(),
            'attendanceUrl' => route('attendance.edit', $outing),
        ];
    }

    /**
     * Active members (plus those already seated on this plan), with their appel status and the boat they
     * already sit on for this outing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function members(Outing $outing, ?CrewPlan $plan, int $associationId): Collection
    {
        $statuses = $outing->attendances->pluck('status', 'member_id');
        $assignedHere = $plan?->assignments->pluck('member_id') ?? collect();

        $elsewhere = CrewAssignment::query()
            ->whereIn('crew_plan_id', $outing->crewPlans->where('id', '!=', $plan?->id)->pluck('id'))
            ->get(['crew_plan_id', 'member_id'])
            ->mapWithKeys(fn (CrewAssignment $assignment) => [
                $assignment->member_id => $outing->crewPlans->firstWhere('id', $assignment->crew_plan_id)->boat->name,
            ]);

        return Member::query()
            ->forAssociation($associationId)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $assignedHere))
            ->with('crewRoles')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $member->full_name,
                ...$this->presenter->member($member),
                'kg' => $member->weight_kg !== null ? (float) $member->weight_kg : null,
                'cm' => $member->height_cm,
                'level' => $member->level->label(),
                'roles' => $member->orderedCrewRoles()->pluck('code')->all(),
                'status' => $statuses->get($member->id)?->value,
                'elsewhere' => $elsewhere->get($member->id),
            ])
            ->values();
    }
}
