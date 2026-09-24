<?php

namespace App\Models;

use App\Enums\BwaPlacement;
use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'crew_plan_id', 'boat_position_id', 'member_id', 'bwa_placement'])]
class CrewAssignment extends Model
{
    use HasClientUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'bwa_placement' => BwaPlacement::class,
        ];
    }

    /** @return BelongsTo<CrewPlan, $this> */
    public function crewPlan(): BelongsTo
    {
        return $this->belongsTo(CrewPlan::class);
    }

    /** @return BelongsTo<BoatPosition, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(BoatPosition::class, 'boat_position_id');
    }

    /**
     * Includes deleted members so that past plans keep showing who sailed.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }
}
