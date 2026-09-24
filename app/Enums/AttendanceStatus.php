<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Excuse = 'excuse';
    case Retard = 'retard';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Absent => 'Absent',
            self::Excuse => 'Excusé',
            self::Retard => 'Retard',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => '#10B981',
            self::Absent => '#EF4444',
            self::Excuse => '#3B82F6',
            self::Retard => '#F59E0B',
        };
    }

    /** Light background used for pills and counters. */
    public function background(): string
    {
        return match ($this) {
            self::Present => '#D1FAE5',
            self::Absent => '#FEE2E2',
            self::Excuse => '#DBEAFE',
            self::Retard => '#FEF3C7',
        };
    }

    /** Darker shade of the colour, readable on the light background. */
    public function textColor(): string
    {
        return match ($this) {
            self::Present => '#047857',
            self::Absent => '#B91C1C',
            self::Excuse => '#1D4ED8',
            self::Retard => '#B45309',
        };
    }

    /** Counts as "on site" (available for a crew plan). */
    public function isOnSite(): bool
    {
        return $this === self::Present || $this === self::Retard;
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
