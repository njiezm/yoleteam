<?php

namespace App\Models;

use App\Enums\RaceResultStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['race_stage_id', 'boat_id', 'crew_plan_id', 'rank', 'elapsed_seconds', 'points', 'status', 'notes'])]
class RaceResult extends Model
{
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'elapsed_seconds' => 'integer',
            'points' => 'decimal:2',
            'status' => RaceResultStatus::class,
        ];
    }

    /** Elapsed time formatted as H:MM:SS. @return Attribute<string|null, never> */
    protected function elapsedTime(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->elapsed_seconds === null) {
                return null;
            }

            $s = $this->elapsed_seconds;

            return sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
        });
    }

    /**
     * Parses an "H:MM:SS" string into seconds (null when blank).
     */
    public static function parseElapsed(?string $time): ?int
    {
        $time = trim((string) $time);

        if ($time === '') {
            return null;
        }

        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    public function isClassified(): bool
    {
        return $this->status === RaceResultStatus::Classe;
    }

    /** @return BelongsTo<RaceStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(RaceStage::class, 'race_stage_id');
    }

    /** @return BelongsTo<Boat, $this> */
    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class);
    }

    /** @return BelongsTo<CrewPlan, $this> */
    public function crewPlan(): BelongsTo
    {
        return $this->belongsTo(CrewPlan::class);
    }
}
