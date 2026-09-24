<?php

namespace App\Enums;

enum MemberLevel: string
{
    case Debutant = 'debutant';
    case Intermediaire = 'intermediaire';
    case Confirme = 'confirme';
    case Expert = 'expert';

    public function label(): string
    {
        return match ($this) {
            self::Debutant => 'Débutant',
            self::Intermediaire => 'Intermédiaire',
            self::Confirme => 'Confirmé',
            self::Expert => 'Expert',
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
