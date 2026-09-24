<?php

namespace App\Enums;

enum SyncAction: string
{
    case Upsert = 'upsert';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::Upsert => 'Création / modification',
            self::Delete => 'Suppression',
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
