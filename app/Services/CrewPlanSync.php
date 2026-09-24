<?php

namespace App\Services;

use App\Enums\CrewPlanStatus;
use App\Models\CrewAssignment;
use App\Models\CrewPlan;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the state of a crew plan (configuration, wind, notes, assignments) in one transaction.
 * The full state is sent every time, which keeps the editor simple and makes offline replays idempotent.
 */
class CrewPlanSync
{
    /**
     * @param  array{boat_configuration_id: int, wind_direction?: int|null, wind_strength?: int|null, bwa_side?: string|null, fond_count?: int|null, notes?: string|null, assignments: list<array{position_id: int, member_id: int, bwa_placement?: string|null}>}  $state
     */
    public function sync(CrewPlan $plan, array $state): CrewPlan
    {
        return DB::transaction(function () use ($plan, $state) {
            $wanted = collect($state['assignments'])
                ->map(fn (array $row) => [
                    'boat_position_id' => (int) $row['position_id'],
                    'member_id' => (int) $row['member_id'],
                    'bwa_placement' => $row['bwa_placement'] ?? null,
                ])
                ->keyBy('boat_position_id');

            $current = $plan->assignments()->get()->keyBy('boat_position_id');

            // Remove rows that changed first so the partial unique indexes (plan+position, plan+member) never collide on swaps.
            $stale = $current->filter(function (CrewAssignment $assignment) use ($wanted) {
                $row = $wanted->get($assignment->boat_position_id);

                return $row === null
                    || $row['member_id'] !== $assignment->member_id
                    || $row['bwa_placement'] !== $assignment->bwa_placement?->value;
            });

            $stale->each->delete();

            $wanted
                ->reject(fn (array $row, int $positionId) => $current->has($positionId) && ! $stale->has($positionId))
                ->each(fn (array $row) => $plan->assignments()->create($row));

            $plan->fill([
                'boat_configuration_id' => $state['boat_configuration_id'],
                'wind_direction' => $state['wind_direction'] ?? null,
                'wind_strength' => $state['wind_strength'] ?? null,
                'bwa_side' => $state['bwa_side'] ?? $plan->bwa_side ?? 'babord',
                'fond_count' => $state['fond_count'] ?? $plan->fond_count,
                'notes' => $state['notes'] ?? $plan->notes,
            ]);

            $changed = $plan->isDirty() || $stale->isNotEmpty() || $wanted->count() !== $current->count();

            // Editing a validated plan reopens it as a new draft version.
            if ($changed && $plan->isValidated()) {
                $plan->forceFill([
                    'status' => CrewPlanStatus::Brouillon,
                    'validated_at' => null,
                    'validated_by' => null,
                    'version' => $plan->version + 1,
                ]);
            }

            if ($changed) {
                $plan->touch();
            }

            $plan->save();

            return $plan->unsetRelation('assignments');
        });
    }
}
