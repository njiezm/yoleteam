<?php

namespace App\Models;

use App\Enums\CrewZone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global reference data (not scoped per association).
 */
#[Fillable(['code', 'label', 'zone', 'color', 'sort_order'])]
class CrewRole extends Model
{
    public const PATRON = 'patron';

    public const AIDE_PATRON = 'aide_patron';

    public const PREMIERE_CORDE = 'premiere_corde';

    public const DEUXIEME_CORDE = 'deuxieme_corde';

    public const ECOUTE = 'ecoute';

    public const DRESSEUR = 'dresseur';

    public const ECOPEUR = 'ecopeur';

    protected function casts(): array
    {
        return [
            'zone' => CrewZone::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsToMany<Member, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot('is_preferred')
            ->withTimestamps();
    }

    /** @return HasMany<BoatPosition, $this> */
    public function boatPositions(): HasMany
    {
        return $this->hasMany(BoatPosition::class);
    }

    /** Short label printed on empty seats of the boat drawing. */
    public function short(): string
    {
        return match ($this->code) {
            self::PATRON => 'PAT',
            self::AIDE_PATRON => 'PAG',
            self::PREMIERE_CORDE => 'C1',
            self::DEUXIEME_CORDE => 'C2',
            self::ECOUTE => 'ÉCO',
            self::DRESSEUR => 'BD',
            self::ECOPEUR => 'ÉCP',
            default => mb_strtoupper(mb_substr($this->label, 0, 3)),
        };
    }

    public static function idsByCode(): array
    {
        return static::query()->pluck('id', 'code')->all();
    }
}
