<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['boat_id', 'name', 'sail_count', 'bwa_count', 'is_default'])]
class BoatConfiguration extends Model
{
    protected function casts(): array
    {
        return [
            'sail_count' => 'integer',
            'bwa_count' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Boat, $this> */
    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class);
    }

    /** @return HasMany<BoatPosition, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(BoatPosition::class)->orderBy('sort_order');
    }

    /** @return HasMany<CrewPlan, $this> */
    public function crewPlans(): HasMany
    {
        return $this->hasMany(CrewPlan::class);
    }
}
