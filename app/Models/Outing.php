<?php

namespace App\Models;

use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Enums\SeaState;
use App\Models\Concerns\BelongsToAssociation;
use App\Models\Concerns\HasClientUuid;
use App\Services\CrewPlanPresenter;
use Database\Factories\OutingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'association_id', 'uuid', 'type', 'title', 'date', 'start_time', 'end_time', 'location',
    'wind_direction', 'wind_strength', 'wind_gusts', 'sea_state', 'swell_m', 'weather',
    'status', 'race_stage_id', 'notes', 'created_by',
])]
class Outing extends Model
{
    /** @use HasFactory<OutingFactory> */
    use BelongsToAssociation, HasClientUuid, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => OutingType::class,
            'status' => OutingStatus::class,
            'date' => 'date',
            'wind_direction' => 'integer',
            'wind_strength' => 'integer',
            'wind_gusts' => 'integer',
            'sea_state' => SeaState::class,
            'swell_m' => 'decimal:1',
        ];
    }

    /** "06:00 – 08:30" @return Attribute<string|null, never> */
    protected function timeRange(): Attribute
    {
        return Attribute::get(function (): ?string {
            $start = $this->start_time ? substr($this->start_time, 0, 5) : null;
            $end = $this->end_time ? substr($this->end_time, 0, 5) : null;

            return $start && $end ? "{$start} – {$end}" : ($start ?? $end);
        });
    }

    /**
     * The outing the day revolves around: today's, otherwise the next one, otherwise the latest past one.
     */
    public static function current(int $associationId): ?self
    {
        $query = fn () => static::query()
            ->forAssociation($associationId)
            ->where('status', '!=', OutingStatus::Annulee);

        return $query()->whereDate('date', today())->orderBy('start_time')->first()
            ?? $query()->whereDate('date', '>', today())->orderBy('date')->orderBy('start_time')->first()
            ?? $query()->whereDate('date', '<', today())->orderByDesc('date')->first();
    }

    /** "E-NE 15 nds (rafales 22) · mer agitée · houle 1,5 m" — null when nothing was entered. */
    public function conditionsSummary(): ?string
    {
        $wind = $this->wind_direction !== null || $this->wind_strength !== null
            ? trim('Vent '.CrewPlanPresenter::windLabel($this->wind_direction).($this->wind_strength !== null ? " {$this->wind_strength} nds" : '').($this->wind_gusts ? " (rafales {$this->wind_gusts})" : ''))
            : null;

        return collect([
            $wind,
            $this->sea_state ? 'mer '.mb_strtolower($this->sea_state->label()) : null,
            $this->swell_m !== null ? 'houle '.str_replace('.', ',', (string) (float) $this->swell_m).' m' : null,
            $this->weather,
        ])->filter()->join(' · ') ?: null;
    }

    public function isToday(): bool
    {
        return $this->date->isToday();
    }

    /** @return BelongsTo<RaceStage, $this> */
    public function raceStage(): BelongsTo
    {
        return $this->belongsTo(RaceStage::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<CrewPlan, $this> */
    public function crewPlans(): HasMany
    {
        return $this->hasMany(CrewPlan::class);
    }

    /** @param Builder<static> $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('date', '>=', today())->orderBy('date');
    }

    /** @param Builder<static> $query */
    public function scopePast(Builder $query): void
    {
        $query->whereDate('date', '<', today())->orderByDesc('date');
    }
}
