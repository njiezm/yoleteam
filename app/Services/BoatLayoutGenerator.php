<?php

namespace App\Services;

use App\Enums\BoatSide;
use App\Models\BoatConfiguration;
use App\Models\BoatPosition;
use App\Models\CrewRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates the default set of crew positions (with top-down SVG coordinates)
 * for a yole configuration.
 *
 * Coordinates are percentages of the drawing: y = 0 bow, y = 100 stern,
 * x = 50 hull axis. Dresseurs sit outside the hull on the bwa (babord x≈15, tribord x≈85).
 */
class BoatLayoutGenerator
{
    public const BWA_Y_START = 30.0;

    public const BWA_Y_END = 78.0;

    public const BABORD_X = 15.0;

    public const TRIBORD_X = 85.0;

    /**
     * Pure layout description (no DB access), usable for previews.
     *
     * @return list<array{role: string, code: string, label: string, side: BoatSide, bwa_index: int|null, x: float, y: float, is_optional: bool}>
     */
    public function layout(int $sailCount, int $bwaCount): array
    {
        $positions = [];

        // Avant : cordes (focs / voiles d'avant).
        $positions[] = $this->position(CrewRole::PREMIERE_CORDE, 'premiere_corde', '1ère corde', BoatSide::Centre, null, 50, 10);

        if ($sailCount >= 2) {
            $positions[] = $this->position(CrewRole::DEUXIEME_CORDE, 'deuxieme_corde', '2ème corde', BoatSide::Centre, null, 50, 20);
        }

        // Bwa : dresseurs de chaque côté, du plus avant (1) au plus arrière.
        for ($i = 1; $i <= $bwaCount; $i++) {
            $y = $bwaCount > 1
                ? self::BWA_Y_START + ($i - 1) * (self::BWA_Y_END - self::BWA_Y_START) / ($bwaCount - 1)
                : (self::BWA_Y_START + self::BWA_Y_END) / 2;

            foreach ([BoatSide::Babord, BoatSide::Tribord] as $side) {
                $positions[] = $this->position(
                    CrewRole::DRESSEUR,
                    "dresseur_{$side->value}_{$i}",
                    "Dresseur {$side->label()} {$i}",
                    $side,
                    $i,
                    $side === BoatSide::Babord ? self::BABORD_X : self::TRIBORD_X,
                    round($y, 2),
                );
            }
        }

        // Gréement / coque (axe central).
        $positions[] = $this->position(CrewRole::ECOUTE, 'ecoute', 'Écoute', BoatSide::Centre, null, 50, 60);
        $positions[] = $this->position(CrewRole::ECOPEUR, 'ecopeur', 'Écopeur', BoatSide::Centre, null, 50, 70);

        // Arrière : aide(s)-patron et patron.
        if ($sailCount >= 2) {
            $positions[] = $this->position(CrewRole::AIDE_PATRON, 'aide_patron_1', 'Aide-patron 1', BoatSide::Babord, null, 40, 84);
            $positions[] = $this->position(CrewRole::AIDE_PATRON, 'aide_patron_2', 'Aide-patron 2', BoatSide::Tribord, null, 60, 84, true);
        } else {
            $positions[] = $this->position(CrewRole::AIDE_PATRON, 'aide_patron_1', 'Aide-patron', BoatSide::Centre, null, 50, 84);
        }

        $positions[] = $this->position(CrewRole::PATRON, 'patron', 'Patron', BoatSide::Centre, null, 50, 92);

        return $positions;
    }

    /**
     * Persist the positions for a configuration.
     * With $replace = true, existing positions are deleted first (fails if assignments reference them).
     *
     * @return Collection<int, BoatPosition>
     */
    public function generate(BoatConfiguration $configuration, bool $replace = true): Collection
    {
        $roleIds = CrewRole::idsByCode();

        return DB::transaction(function () use ($configuration, $replace, $roleIds) {
            if ($replace) {
                $configuration->positions()->delete();
            }

            $created = collect();

            foreach ($this->layout($configuration->sail_count, $configuration->bwa_count) as $index => $spec) {
                $created->push($configuration->positions()->updateOrCreate(
                    ['code' => $spec['code']],
                    [
                        'crew_role_id' => $roleIds[$spec['role']] ?? throw new \RuntimeException("Crew role [{$spec['role']}] missing: run CrewRoleSeeder."),
                        'label' => $spec['label'],
                        'side' => $spec['side'],
                        'bwa_index' => $spec['bwa_index'],
                        'sort_order' => ($index + 1) * 10,
                        'x' => $spec['x'],
                        'y' => $spec['y'],
                        'is_optional' => $spec['is_optional'],
                    ],
                ));
            }

            return $created;
        });
    }

    private function position(string $role, string $code, string $label, BoatSide $side, ?int $bwaIndex, float $x, float $y, bool $optional = false): array
    {
        return [
            'role' => $role,
            'code' => $code,
            'label' => $label,
            'side' => $side,
            'bwa_index' => $bwaIndex,
            'x' => $x,
            'y' => $y,
            'is_optional' => $optional,
        ];
    }
}
