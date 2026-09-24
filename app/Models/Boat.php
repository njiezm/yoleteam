<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAssociation;
use Database\Factories\BoatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['association_id', 'name', 'sponsor', 'hull_color', 'length_m', 'photo_path', 'notes', 'is_active'])]
class Boat extends Model
{
    /** @use HasFactory<BoatFactory> */
    use BelongsToAssociation, HasFactory;

    protected function casts(): array
    {
        return [
            'length_m' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public const HULL_COLORS = [
        'rouge' => '#E11D48',
        'bleu' => '#0EA5E9',
        'jaune' => '#F5B700',
        'vert' => '#16A34A',
        'orange' => '#F97316',
        'violet' => '#7C3AED',
        'blanc' => '#94A3B8',
        'noir' => '#0F172A',
    ];

    /** Hex colour used for the hull outline and badges. */
    public function color(): string
    {
        return self::HULL_COLORS[$this->hull_color] ?? '#0B2545';
    }

    /**
     * The default configuration, falling back to the first one (uses the loaded `configurations` relation).
     */
    public function primaryConfiguration(): ?BoatConfiguration
    {
        return $this->configurations->firstWhere('is_default', true) ?? $this->configurations->first();
    }

    /** @return HasMany<BoatConfiguration, $this> */
    public function configurations(): HasMany
    {
        return $this->hasMany(BoatConfiguration::class);
    }

    /** @return HasOne<BoatConfiguration, $this> */
    public function defaultConfiguration(): HasOne
    {
        return $this->hasOne(BoatConfiguration::class)->where('is_default', true);
    }

    /** @return HasMany<CrewPlan, $this> */
    public function crewPlans(): HasMany
    {
        return $this->hasMany(CrewPlan::class);
    }

    /** @return HasMany<RaceResult, $this> */
    public function raceResults(): HasMany
    {
        return $this->hasMany(RaceResult::class);
    }
}
