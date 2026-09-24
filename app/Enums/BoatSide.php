<?php

namespace App\Enums;

enum BoatSide: string
{
    case Babord = 'babord';
    case Tribord = 'tribord';
    case Centre = 'centre';

    public function label(): string
    {
        return match ($this) {
            self::Babord => 'Bâbord',
            self::Tribord => 'Tribord',
            self::Centre => 'Centre',
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
