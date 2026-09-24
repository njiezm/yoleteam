<?php

namespace App\Models;

use App\Enums\BoatSide;
use App\Enums\CrewPlanStatus;
use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid', 'outing_id', 'boat_id', 'boat_configuration_id', 'wind_direction', 'wind_strength', 'bwa_side', 'fond_count',
    'status', 'validated_at', 'validated_by', 'version', 'notes', 'created_by',
])]
class CrewPlan extends Model
{
    use HasClientUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => CrewPlanStatus::class,
            'wind_direction' => 'integer',
            'wind_strength' => 'integer',
            'bwa_side' => BoatSide::class,
            'fond_count' => 'integer',
            'validated_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Outing, $this> */
    public function outing(): BelongsTo
    {
        return $this->belongsTo(Outing::class);
    }

    /** @return BelongsTo<Boat, $this> */
    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class);
    }

    /** @return BelongsTo<BoatConfiguration, $this> */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(BoatConfiguration::class, 'boat_configuration_id');
    }

    /** @return HasMany<CrewAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValidated(): bool
    {
        return $this->status === CrewPlanStatus::Valide;
    }

    public function validate(User $by): void
    {
        $this->forceFill([
            'status' => CrewPlanStatus::Valide,
            'validated_at' => now(),
            'validated_by' => $by->getKey(),
        ])->save();
    }
}
