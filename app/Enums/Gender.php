<?php

namespace App\Enums;

enum Gender: string
{
    case F = 'f';
    case M = 'm';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::F => 'Femme',
            self::M => 'Homme',
            self::Other => 'Autre',
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
