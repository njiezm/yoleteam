<?php

namespace App\Enums;

enum RaceType: string
{
    case TourDesYoles = 'tour_des_yoles';
    case Regate = 'regate';
    case Championnat = 'championnat';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::TourDesYoles => 'Tour des Yoles',
            self::Regate => 'Régate',
            self::Championnat => 'Championnat',
            self::Autre => 'Autre',
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
