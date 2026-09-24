<?php

namespace App\Models;

use App\Enums\BoatSide;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A seat on the top-down boat drawing. x/y are percentages:
 * y = 0 bow, y = 100 stern; x = 50 hull axis (dresseurs sit outside the hull on the bwa).
 */
#[Fillable([
    'boat_configuration_id', 'crew_role_id', 'code', 'label', 'side', 'bwa_index',
    'sort_order', 'x', 'y', 'is_optional',
])]
class BoatPosition extends Model
{
    protected function casts(): array
    {
        return [
            'side' => BoatSide::class,
            'bwa_index' => 'integer',
            'sort_order' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'is_optional' => 'boolean',
        ];
    }

    /** @return BelongsTo<BoatConfiguration, $this> */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(BoatConfiguration::class, 'boat_configuration_id');
    }

    /** @return BelongsTo<CrewRole, $this> */
    public function crewRole(): BelongsTo
    {
        return $this->belongsTo(CrewRole::class);
    }

    /** @return HasMany<CrewAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }
}
