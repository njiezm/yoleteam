<?php

namespace App\Enums;

enum CrewZone: string
{
    case Arriere = 'arriere';
    case Avant = 'avant';
    case Greement = 'greement';
    case Bwa = 'bwa';
    case Coque = 'coque';

    public function label(): string
    {
        return match ($this) {
            self::Arriere => 'Arrière',
            self::Avant => 'Avant',
            self::Greement => 'Gréement',
            self::Bwa => 'Bwa',
            self::Coque => 'Coque',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Arriere => '#F5B700',
            self::Avant => '#8B5CF6',
            self::Greement => '#F97316',
            self::Bwa => '#10B981',
            self::Coque => '#06B6D4',
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
