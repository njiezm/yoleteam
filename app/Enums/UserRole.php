<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Patron = 'patron';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Patron => 'Patron',
            self::SuperAdmin => 'Super admin',
        };
    }

    /**
     * Roles an association admin may grant from the settings page (never super admin).
     *
     * @return list<self>
     */
    public static function associationRoles(): array
    {
        return [self::Admin, self::Patron];
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
