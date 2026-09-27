<?php

namespace App\Services;

use App\Enums\OutingType;
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
     * @param  CrewPlan|null  $copyFrom  plan not created yet: crew of the previous race of the same boat, seated by default
     * @return array<string, mixed>
     */
    public function build(Outing $outing, Boat $boat, ?CrewPlan $plan, int $associationId, int $raceNumber = 1, ?CrewPlan $copyFrom = null): array
    {
        $boat->loadMissing('configurations.positions.crewRole');
        $outing->loadMissing(['crewPlans.boat', 'attendances']);
        $plan?->loadMissing('assignments.position');
        $raceNumber = $plan?->race_number ?? $raceNumber;
        $source = $plan ?? $copyFrom;

        $configuration = $source?->configuration
            ?? $boat->configurations->firstWhere('is_default', true)
            ?? $boat->configurations->first();
        $raceLabel = $outing->type === OutingType::Regate ? ' · Manche '.$raceNumber : '';

        return [
            'plan' => [
                'id' => $plan?->id,
                'uuid' => $plan?->uuid,
                'boat_name' => $boat->name,
                'label' => $boat->name.$raceLabel.' · '.$outing->title,
                'status' => $plan?->status->value ?? 'brouillon',
                'version' => $plan?->version ?? 1,
                'configuration_id' => $source?->boat_configuration_id ?? $configuration?->id,
                'wind_direction' => $source?->wind_direction ?? $outing->wind_direction,
                'wind_strength' => $source?->wind_strength ?? $outing->wind_strength,
                'bwa_side' => $source?->bwa_side?->value ?? 'babord',
                'fond_count' => $source?->fond_count ?? 1,
                'max_fonds' => BoatLayoutGenerator::MAX_FONDS,
                'update_url' => $plan ? route('crew-plans.update', [$outing, $plan]) : null,
                // Plans created offline carry what the server needs to create them on sync.
                'create' => $plan ? null : ['outing_uuid' => $outing->uuid, 'boat_id' => $boat->id, 'race_number' => $raceNumber],
            ],
            'boatColor' => $boat->color(),
            'configurations' => $boat->configurations
                ->map(fn (BoatConfiguration $configuration) => $this->presenter->configuration($configuration))
                ->values(),
            'roles' => $this->presenter->roles(),
            'members' => $this->members($outing, $plan, $associationId, $raceNumber),
            'assignments' => (object) ($source ? $this->presenter->assignments($source) : []),
            'attendanceRecorded' => $outing->attendances->isNotEmpty(),
            'attendanceUrl' => route('attendance.edit', $outing),
            'shareCrew' => (bool) $outing->share_crew,
            'shareUrl' => route('outings.share-crew.update', $outing),
            'outingUuid' => $outing->uuid,
            'boatCount' => $outing->crewPlans->where('race_number', $raceNumber)->count(),
        ];
    }

    /**
     * Editor data for a boat of an outing that only exists on the device (created offline): the page fills in
     * the outing uuid, title and wind from the queued outing.
     *
     * @return array<string, mixed>
     */
    public function template(Boat $boat, int $associationId): array
    {
        $boat->loadMissing('configurations.positions.crewRole');
        $configuration = $boat->configurations->firstWhere('is_default', true) ?? $boat->configurations->first();

        return [
            'plan' => [
                'uuid' => null,
                'label' => $boat->name,
                'status' => 'brouillon',
                'version' => 1,
                'configuration_id' => $configuration?->id,
                'wind_direction' => null,
                'wind_strength' => null,
                'bwa_side' => 'babord',
                'fond_count' => 1,
                'max_fonds' => BoatLayoutGenerator::MAX_FONDS,
                'update_url' => null,
                'create' => ['outing_uuid' => null, 'boat_id' => $boat->id],
            ],
            'boatColor' => $boat->color(),
            'configurations' => $boat->configurations
                ->map(fn (BoatConfiguration $configuration) => $this->presenter->configuration($configuration))
                ->values(),
            'roles' => $this->presenter->roles(),
            'members' => [],
            'assignments' => (object) [],
            'attendanceRecorded' => false,
            'attendanceUrl' => null,
        ];
    }

    /**
     * Active members (plus those already seated on this plan), with their appel status and the boat they
     * already sit on for this outing (in the same race: a rower can change boat between two races).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function members(?Outing $outing, ?CrewPlan $plan, int $associationId, int $raceNumber = 1): Collection
    {
        $statuses = $outing?->attendances->pluck('status', 'member_id') ?? collect();
        $assignedHere = $plan?->assignments->pluck('member_id') ?? collect();
        $raceNumber = $plan?->race_number ?? $raceNumber;

        $elsewhere = CrewAssignment::query()
            ->whereIn('crew_plan_id', $outing?->crewPlans->where('id', '!=', $plan?->id)->where('race_number', $raceNumber)->pluck('id') ?? [])
            ->get(['crew_plan_id', 'member_id'])
            ->mapWithKeys(fn (CrewAssignment $assignment) => [
                $assignment->member_id => $outing?->crewPlans->firstWhere('id', $assignment->crew_plan_id)?->boat->name,
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
                'certificate' => (bool) $member->medical_certificate,
                'roles' => $member->orderedCrewRoles()->pluck('code')->all(),
                'status' => $statuses->get($member->id)?->value,
                'elsewhere' => $elsewhere->get($member->id),
            ])
            ->values();
    }
}
