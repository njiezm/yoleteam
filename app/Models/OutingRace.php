<?php

namespace App\Models;

use App\Enums\RaceOutcome;
use Database\Factories\OutingRaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One race (manche) of a race outing.
 */
#[Fillable(['outing_id', 'number', 'place', 'result', 'points'])]
class OutingRace extends Model
{
    /** @use HasFactory<OutingRaceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'place' => 'integer',
            'result' => RaceOutcome::class,
            'points' => 'integer',
        ];
    }

    /** Chip label: "3ᵉ" when classé, otherwise "C", "A" or "D". */
    public function summary(): string
    {
        if ($this->result !== RaceOutcome::Classe) {
            return $this->result->shortLabel();
        }

        return $this->place !== null ? $this->place.'ᵉ' : '—';
    }

    /** @return BelongsTo<Outing, $this> */
    public function outing(): BelongsTo
    {
        return $this->belongsTo(Outing::class);
    }
}
