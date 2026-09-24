<?php

namespace App\Enums;

enum CrewPlanStatus: string
{
    case Brouillon = 'brouillon';
    case Valide = 'valide';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Valide => 'Validé',
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
