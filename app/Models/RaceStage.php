<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['race_id', 'number', 'name', 'date', 'start_location', 'end_location', 'distance_nm'])]
class RaceStage extends Model
{
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'date' => 'date',
            'distance_nm' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Race, $this> */
    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    /** @return HasMany<RaceResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(RaceResult::class)->orderBy('rank');
    }

    /** @return HasMany<Outing, $this> */
    public function outings(): HasMany
    {
        return $this->hasMany(Outing::class);
    }
}
