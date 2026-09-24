<?php

namespace App\Models;

use App\Enums\RaceType;
use App\Models\Concerns\BelongsToAssociation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

#[Fillable(['association_id', 'name', 'type', 'season', 'start_date', 'end_date', 'location', 'notes'])]
class Race extends Model
{
    use BelongsToAssociation;

    protected function casts(): array
    {
        return [
            'type' => RaceType::class,
            'season' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function isUpcoming(): bool
    {
        return $this->start_date->gte(today());
    }

    public function isInProgress(): bool
    {
        return $this->start_date->lte(today()) && ($this->end_date ?? $this->start_date)->gte(today());
    }

    /**
     * Cumulative points per boat of the association (uses the loaded `results.boat` relation).
     * Low-point scoring: most stages sailed first, then fewest points.
     *
     * @return Collection<int, array{boat: Boat, points: float, stages: int}>
     */
    public function standings(): Collection
    {
        return $this->results
            ->groupBy('boat_id')
            ->map(fn (Collection $results) => [
                'boat' => $results->first()->boat,
                'points' => (float) $results->sum('points'),
                'stages' => $results->count(),
            ])
            ->sortBy([['stages', 'desc'], ['points', 'asc']])
            ->values();
    }

    /** @return HasMany<RaceStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(RaceStage::class)->orderBy('number');
    }

    /** @return HasManyThrough<RaceResult, RaceStage, $this> */
    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(RaceResult::class, RaceStage::class);
    }
}
