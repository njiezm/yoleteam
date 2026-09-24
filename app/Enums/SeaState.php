<?php

namespace App\Enums;

/**
 * Sea state, Douglas scale wording used by Météo-France marine forecasts.
 */
enum SeaState: string
{
    case Calme = 'calme';
    case Ridee = 'ridee';
    case Belle = 'belle';
    case PeuAgitee = 'peu_agitee';
    case Agitee = 'agitee';
    case Forte = 'forte';
    case TresForte = 'tres_forte';
    case Grosse = 'grosse';

    public function label(): string
    {
        return match ($this) {
            self::Calme => 'Calme',
            self::Ridee => 'Ridée',
            self::Belle => 'Belle',
            self::PeuAgitee => 'Peu agitée',
            self::Agitee => 'Agitée',
            self::Forte => 'Forte',
            self::TresForte => 'Très forte',
            self::Grosse => 'Grosse',
        };
    }

    /** Typical wave height, shown as a hint. */
    public function waves(): string
    {
        return match ($this) {
            self::Calme => '0 m',
            self::Ridee => '0 – 0,1 m',
            self::Belle => '0,1 – 0,5 m',
            self::PeuAgitee => '0,5 – 1,25 m',
            self::Agitee => '1,25 – 2,5 m',
            self::Forte => '2,5 – 4 m',
            self::TresForte => '4 – 6 m',
            self::Grosse => '6 – 9 m',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label().' ('.$case->waves().')', self::cases()),
        );
    }
}
