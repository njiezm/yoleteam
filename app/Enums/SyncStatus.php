<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Applied = 'applied';
    case Conflict = 'conflict';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Appliquée',
            self::Conflict => 'Conflit',
            self::Rejected => 'Rejetée',
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
