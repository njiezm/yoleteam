<?php

namespace App\Enums;

enum OutingType: string
{
    case Entrainement = 'entrainement';
    case Regate = 'regate';
    case SortieLibre = 'sortie_libre';

    public function label(): string
    {
        return match ($this) {
            self::Entrainement => 'Entraînement',
            self::Regate => 'Course',
            self::SortieLibre => 'Sortie libre',
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
