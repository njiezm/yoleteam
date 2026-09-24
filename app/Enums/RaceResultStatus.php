<?php

namespace App\Enums;

enum RaceResultStatus: string
{
    case Classe = 'classe';
    case Abandon = 'abandon';
    case Disqualifie = 'disqualifie';
    case NonPartant = 'non_partant';

    public function label(): string
    {
        return match ($this) {
            self::Classe => 'Classé',
            self::Abandon => 'Abandon',
            self::Disqualifie => 'Disqualifié',
            self::NonPartant => 'Non partant',
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
