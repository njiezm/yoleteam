<?php

namespace App\Enums;

/**
 * Outcome of one race (manche) of a race outing, and the points it scores (lowest total wins).
 */
enum RaceOutcome: string
{
    case Classe = 'classe';
    case Coule = 'coule';
    case Avarie = 'avarie';
    case Disqualifie = 'disqualifie';

    /** Fleet size: a sunk or damaged yole scores as if it finished last. */
    public const PENALTY_POINTS = 16;

    /** Places that can be selected for a race or a ranking. */
    public const MAX_PLACE = 20;

    public function label(): string
    {
        return match ($this) {
            self::Classe => 'Classé',
            self::Coule => 'Coulé (C)',
            self::Avarie => 'Avarie (A)',
            self::Disqualifie => 'Disqualifié (D)',
        };
    }

    /** Button / chip label: "Classé", "C", "A", "D". */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Classe => 'Classé',
            self::Coule => 'C',
            self::Avarie => 'A',
            self::Disqualifie => 'D',
        };
    }

    /** Whether the points are typed in (disqualification) rather than computed. */
    public function hasTypedPoints(): bool
    {
        return $this === self::Disqualifie;
    }

    /**
     * Classé: the place; coulé / avarie: the penalty; disqualifié: the points typed in.
     */
    public function points(?int $place, ?int $typedPoints = null): ?int
    {
        return match ($this) {
            self::Classe => $place,
            self::Coule, self::Avarie => self::PENALTY_POINTS,
            self::Disqualifie => $typedPoints,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label(), self::cases()),
        );
    }
}
