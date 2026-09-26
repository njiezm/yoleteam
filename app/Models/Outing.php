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
    'status', 'race_stage_id', 'notes', 'notes_before', 'notes_during', 'notes_after', 'distance_nm',
    'day_rank', 'stage_rank', 'general_rank', 'created_by',
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
            'distance_nm' => 'decimal:1',
            'day_rank' => 'integer',
            'stage_rank' => 'integer',
            'general_rank' => 'integer',
        ];
    }

    /** Minutes between start and end time, null until both are entered (end after start). */
    public function durationMinutes(): ?int
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        [$startHours, $startMinutes] = array_map('intval', explode(':', substr($this->start_time, 0, 5)));
        [$endHours, $endMinutes] = array_map('intval', explode(':', substr($this->end_time, 0, 5)));
        $minutes = ($endHours * 60 + $endMinutes) - ($startHours * 60 + $startMinutes);

        return $minutes > 0 ? $minutes : null;
    }

    /** "2 h 30", "3 h", "45 min" — null when the duration is unknown. */
    public function durationLabel(): ?string
    {
        $minutes = $this->durationMinutes();

        if ($minutes === null) {
            return null;
        }

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? "{$hours} h" : sprintf('%d h %02d', $hours, $rest);
    }

    /** Average speed in knots (distance in nautical miles / duration in hours), 1 decimal. */
    public function averageSpeedKnots(): ?float
    {
        $minutes = $this->durationMinutes();

        if ($minutes === null || $this->distance_nm === null || (float) $this->distance_nm <= 0) {
            return null;
        }

        return round((float) $this->distance_nm / ($minutes / 60), 1);
    }

    /** Sum of the points of every race of the outing (lowest wins); null when no race has points. */
    public function combiPoints(): ?int
    {
        $points = $this->races->whereNotNull('points');

        return $points->isEmpty() ? null : (int) $points->sum('points');
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

    /** @return HasMany<OutingRace, $this> */
    public function races(): HasMany
    {
        return $this->hasMany(OutingRace::class)->orderBy('number');
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
