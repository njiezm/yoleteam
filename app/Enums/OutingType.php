<?php

namespace App\Enums;

enum OutingType: string
{
    case Entrainement = 'entrainement';
    case Regate = 'regate';
    case Tdy = 'tdy';

    public function label(): string
    {
        return match ($this) {
            self::Entrainement => 'Entraînement',
            self::Regate => 'Course',
            self::Tdy => 'TDY (Tour des yoles)',
        };
    }

    /** Race and TDY outings record results (places and rankings). */
    public function hasResults(): bool
    {
        return $this !== self::Entrainement;
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
