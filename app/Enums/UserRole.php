<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Patron = 'patron';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Patron => 'Patron',
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
