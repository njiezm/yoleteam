<?php

namespace App\Enums;

enum BwaPlacement: string
{
    case Interieur = 'interieur';
    case Milieu = 'milieu';
    case Exterieur = 'exterieur';

    public function label(): string
    {
        return match ($this) {
            self::Interieur => 'Intérieur',
            self::Milieu => 'Milieu',
            self::Exterieur => 'Extérieur',
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
