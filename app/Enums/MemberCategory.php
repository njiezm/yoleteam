<?php

namespace App\Enums;

enum MemberCategory: string
{
    case Jeune = 'jeune';
    case Senior = 'senior';
    case Veteran = 'veteran';

    public function label(): string
    {
        return match ($this) {
            self::Jeune => 'Jeune',
            self::Senior => 'Senior',
            self::Veteran => 'Vétéran',
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
