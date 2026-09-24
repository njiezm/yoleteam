<?php

namespace App\Services;

use App\Enums\BoatSide;
use App\Models\Boat;
use App\Models\BoatConfiguration;
use App\Models\BoatPosition;
use App\Models\CrewAssignment;
use App\Models\CrewPlan;
use App\Models\CrewRole;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * Shapes boats, configurations and crew plans into the arrays consumed by the
 * client-side yole drawing (resources/js/yole.js) and the crew plan editor.
 */
class CrewPlanPresenter
{
    /** @var array<string, array{label: string, short: string, color: string, zone: string}>|null */
    private ?array $roles = null;

    /** @return array<string, array{label: string, short: string, color: string, zone: string}> */
    public function roles(): array
    {
        return $this->roles ??= CrewRole::query()->orderBy('sort_order')->get()
            ->mapWithKeys(fn (CrewRole $role) => [$role->code => [
                'label' => $role->label,
                'short' => $role->short(),
                'color' => $role->color,
                'zone' => $role->zone->label(),
            ]])->all();
    }

    /**
     * @return array{id: int, name: string, sail_count: int, bwa_count: int, is_default: bool, positions: list<array<string, mixed>>}
     */
    public function configuration(BoatConfiguration $configuration): array
    {
        $configuration->loadMissing('positions.crewRole');

        return [
            'id' => $configuration->id,
            'name' => $configuration->name,
            'sail_count' => $configuration->sail_count,
            'bwa_count' => $configuration->bwa_count,
            'is_default' => $configuration->is_default,
            'positions' => $configuration->positions->map(fn (BoatPosition $position) => [
                'id' => $position->id,
                'code' => $position->code,
                'label' => $position->label,
                'role' => $position->crewRole->code,
                'side' => $position->side->value,
                'bwa' => $position->bwa_index,
                'x' => $position->x,
                'y' => $position->y,
                'optional' => $position->is_optional,
            ])->values()->all(),
        ];
    }

    /**
     * Assignments of a plan keyed by position code.
     *
     * @return array<string, array{member_id: int, placement: string|null}>
     */
    public function assignments(CrewPlan $plan): array
    {
        $plan->loadMissing('assignments.position', 'assignments.member');

        // A deleted member can no longer be seated: leave the seat free in the editor and the drawing.
        return $plan->assignments
            ->reject(fn (CrewAssignment $assignment) => $assignment->member === null || $assignment->member->trashed())
            ->mapWithKeys(fn (CrewAssignment $assignment) => [$assignment->position->code => [
                'member_id' => $assignment->member_id,
                'placement' => $assignment->bwa_placement?->value,
            ]])->all();
    }

    /**
     * @return array{initials: string, short: string, name: string, color: string}
     */
    public function member(Member $member): array
    {
        return [
            'initials' => $member->initials,
            'short' => $member->short_name,
            'name' => $member->full_name,
            'color' => $member->color(),
        ];
    }

    /**
     * Data for a read-only <x-yole> drawing.
     *
     * @param  array{labels?: bool, compact?: bool, wind?: bool}  $options
     * @return array<string, mixed>
     */
    public function drawing(Boat $boat, BoatConfiguration $configuration, ?CrewPlan $plan = null, array $options = []): array
    {
        $members = [];

        if ($plan) {
            $plan->loadMissing('assignments.position', 'assignments.member.crewRoles');
            foreach ($plan->assignments as $assignment) {
                $members[$assignment->member_id] = $this->member($assignment->member);
            }
        }

        return [
            'config' => $this->configuration($configuration),
            'roles' => $this->roles(),
            'members' => (object) $members,
            'assignments' => (object) ($plan ? $this->assignments($plan) : []),
            'wind' => ($options['wind'] ?? true) && $plan?->wind_direction !== null
                ? ['dir' => $plan->wind_direction, 'kts' => $plan->wind_strength]
                : null,
            'boatColor' => $boat->color(),
            'bwaSide' => $plan?->bwa_side?->value ?? BoatSide::Babord->value,
            'fondCount' => $plan?->fond_count ?? 1,
            'labels' => $options['labels'] ?? true,
            'compact' => $options['compact'] ?? false,
        ];
    }

    /** Whether a seat is used by the plan (fond / écopeur seats beyond the plan's fond_count are hidden). */
    public static function isSeatUsed(BoatPosition $position, ?CrewPlan $plan): bool
    {
        $fond = BoatLayoutGenerator::fondIndex($position->code);

        return $fond === null || $fond <= ($plan?->fond_count ?? 1);
    }

    /**
     * Informative weight distribution of a plan (declared weights only). All bwa dressés sit on the windward side.
     *
     * @return array{bwa: float, bwa_count: int, avant: float, arriere: float, total: float, filled: int, positions: int}
     */
    public function balance(CrewPlan $plan): array
    {
        $plan->loadMissing('assignments.position', 'assignments.member', 'configuration.positions');

        $balance = ['bwa' => 0.0, 'bwa_count' => 0, 'avant' => 0.0, 'arriere' => 0.0, 'total' => 0.0, 'filled' => 0];

        foreach ($plan->assignments as $assignment) {
            $weight = (float) $assignment->member->weight_kg;
            $position = $assignment->position;

            $balance['filled']++;
            $balance['total'] += $weight;
            $balance[$position->y < 50 ? 'avant' : 'arriere'] += $weight;

            if ($position->bwa_index !== null) {
                $balance['bwa'] += $weight;
                $balance['bwa_count']++;
            }
        }

        return [
            ...$balance,
            'positions' => $plan->configuration->positions->filter(fn (BoatPosition $position) => self::isSeatUsed($position, $plan))->count(),
        ];
    }

    /**
     * Assignments grouped for the printable plan.
     *
     * @return Collection<string, Collection<int, CrewAssignment>>
     */
    public function groupedAssignments(CrewPlan $plan): Collection
    {
        $plan->loadMissing('assignments.position.crewRole', 'assignments.member.crewRoles');

        return $plan->assignments
            ->sortBy(fn (CrewAssignment $assignment) => $assignment->position->sort_order)
            ->groupBy(fn (CrewAssignment $assignment) => match (true) {
                $assignment->position->bwa_index !== null => 'Bwa dressés · au vent '.mb_strtolower(($plan->bwa_side ?? BoatSide::Babord)->label()),
                BoatLayoutGenerator::fondIndex($assignment->position->code) !== null => 'Fonds / écopeurs',
                $assignment->position->y < 50 => 'Avant & voiles',
                default => 'Arrière',
            });
    }

    /** "E-NE" style label for a wind direction in degrees (direction the wind comes from). */
    public static function windLabel(?int $degrees): ?string
    {
        if ($degrees === null) {
            return null;
        }

        $points = ['N', 'N-NE', 'NE', 'E-NE', 'E', 'E-SE', 'SE', 'S-SE', 'S', 'S-SO', 'SO', 'O-SO', 'O', 'O-NO', 'NO', 'N-NO'];

        return $points[(int) round((($degrees % 360) + 360) % 360 / 22.5) % 16];
    }

    /** @return array<int, string> Degrees => label, for wind selects. */
    public static function windOptions(): array
    {
        return collect(range(0, 337.5, 22.5))
            ->mapWithKeys(fn (float $degrees) => [(int) $degrees => self::windLabel((int) $degrees)])
            ->all();
    }
}
