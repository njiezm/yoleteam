<?php

namespace App\Models;

use App\Services\BoatLayoutGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['boat_id', 'name', 'sail_count', 'bwa_count', 'cordes_count', 'ecoute_count', 'pagaie_count', 'is_default'])]
class BoatConfiguration extends Model
{
    protected function casts(): array
    {
        return [
            'sail_count' => 'integer',
            'bwa_count' => 'integer',
            'cordes_count' => 'integer',
            'ecoute_count' => 'integer',
            'pagaie_count' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Boat, $this> */
    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class);
    }

    /** Crew seats excluding the optional fond / écopeur places (uses the loaded positions). */
    public function crewSeatCount(): int
    {
        return $this->positions->filter(fn (BoatPosition $position) => BoatLayoutGenerator::fondIndex($position->code) === null)->count();
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
