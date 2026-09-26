<?php

namespace App\Services;

use App\Enums\BoatSide;
use App\Models\BoatConfiguration;
use App\Models\BoatPosition;
use App\Models\CrewAssignment;
use App\Models\CrewRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Crew positions (with top-down drawing coordinates) of a yole configuration.
 *
 * Crew of a yole ronde: patron and pagaies at the stern, cordes at the bow (2 voiles only), écoutes
 * (one pair per sail), bwa dressés all on the windward side (moved across at each tack; the side is
 * chosen per crew plan), and fonds / écopeurs whose number is chosen per crew plan (up to MAX_FONDS).
 *
 * Coordinates are percentages of the drawing: y = 0 bow, y = 100 stern, x = 50 hull axis.
 * Bwa seats are stored on the babord side; the drawing mirrors them when the plan sails bwa tribord.
 */
class BoatLayoutGenerator
{
    public const MAX_FONDS = 4;

    public const BABORD_X = 15.0;

    /**
     * Usual crew of each rig (patron not included in the pagaies).
     *
     * @return array{bwa_count: int, cordes_count: int, ecoute_count: int, pagaie_count: int}
     */
    public static function defaults(int $sailCount): array
    {
        return $sailCount >= 2
            ? ['bwa_count' => 8, 'cordes_count' => 2, 'ecoute_count' => 4, 'pagaie_count' => 2]
            : ['bwa_count' => 9, 'cordes_count' => 0, 'ecoute_count' => 2, 'pagaie_count' => 2];
    }

    /**
     * Pure layout description (no DB access).
     *
     * @return list<array{role: string, code: string, label: string, side: BoatSide, bwa_index: int|null, x: float, y: float, is_optional: bool}>
     */
    public function layout(BoatConfiguration $configuration): array
    {
        $sails = $configuration->sail_count;
        $counts = [...self::defaults($sails), ...array_filter([
            'bwa_count' => $configuration->bwa_count,
            'cordes_count' => $configuration->cordes_count,
            'ecoute_count' => $configuration->ecoute_count,
            'pagaie_count' => $configuration->pagaie_count,
        ], fn ($value) => $value !== null)];

        $positions = [];
        $step = 4.6; // vertical room of one seat (a name tag) in drawing units

        // Avant : 1ère corde tout à l'avant, 2ème corde juste derrière (2 voiles).
        $cordes = [[CrewRole::PREMIERE_CORDE, 'premiere_corde', '1ère corde'], [CrewRole::DEUXIEME_CORDE, 'deuxieme_corde', '2ème corde']];
        foreach (array_slice($cordes, 0, min(2, $counts['cordes_count'])) as $index => [$role, $code, $label]) {
            $positions[] = $this->position($role, $code, $label, BoatSide::Centre, null, 50, 5 + $index * $step);
        }

        // Écoutes : au niveau de chaque voile, sous le vent (stored on the tribord side, mirrored by the drawing
        // when the bwa dressés sail tribord). Masts: 2 voiles at 15 and 38, misaine forward at 14.
        $ecoutes = max(1, $counts['ecoute_count']);
        $sheets = $sails >= 2
            ? [['ecoute_pv', 'Écoute petite voile', 15, intdiv($ecoutes, 2)], ['ecoute_gv', 'Écoute grande voile', 38, $ecoutes - intdiv($ecoutes, 2)]]
            : [['ecoute', 'Écoute', 14, $ecoutes]];
        $lastSheetY = 0;
        foreach ($sheets as [$prefix, $label, $mastY, $count]) {
            for ($i = 1; $i <= $count; $i++) {
                $y = $mastY + 4 + ($i - 1) * $step;
                $lastSheetY = max($lastSheetY, $y);
                $positions[] = $this->position(CrewRole::ECOUTE, "{$prefix}_{$i}", $count === 1 ? $label : "{$label} {$i}", BoatSide::Tribord, null, 62, $y);
            }
        }

        // Bwa dressés : tous au vent, du plus avant (1) au plus arrière.
        $bwa = max(1, $counts['bwa_count']);
        [$first, $last] = $sails >= 2 ? [24.0, 78.0] : [20.0, 78.0];
        $bwaYs = [];
        for ($i = 1; $i <= $bwa; $i++) {
            $y = round($bwa > 1 ? $first + ($i - 1) * ($last - $first) / ($bwa - 1) : ($first + $last) / 2, 2);
            $bwaYs[] = $y;
            // The first dresseur is "les yeux du patron"; the last one stays in rappel longest when tacking.
            $label = "Bwa dressé {$i}".match (true) {
                $bwa > 1 && $i === 1 => ' (premier)',
                $bwa > 1 && $i === $bwa => ' (dernier)',
                default => '',
            };
            $positions[] = $this->position(CrewRole::DRESSEUR, "bwa_{$i}", $label, BoatSide::Babord, $i, self::BABORD_X, $y);
        }

        // Fonds / écopeurs : dans l'axe, entre deux bwa (not on a pole), aft of the sheets. The crew plan chooses
        // how many of these places are used.
        $fondYs = [];
        for ($i = 1; $i < count($bwaYs); $i++) {
            $middle = round(($bwaYs[$i - 1] + $bwaYs[$i]) / 2, 2);
            if ($middle > $lastSheetY + $step * 0.9) {
                $fondYs[] = $middle;
            }
        }
        while (count($fondYs) < self::MAX_FONDS) {
            $fondYs[] = round((end($fondYs) ?: $lastSheetY) + $step, 2);
        }
        foreach (array_slice($fondYs, 0, self::MAX_FONDS) as $index => $y) {
            $positions[] = $this->position(CrewRole::ECOPEUR, 'fond_'.($index + 1), 'Fond / écopeur '.($index + 1), BoatSide::Centre, null, 50, $y, true);
        }

        // Arrière, après les bwa : les pagaies alignées dans l'axe, puis le patron tout à l'arrière.
        $pagaies = min(3, max(0, $counts['pagaie_count']));
        $y = $last + 4.5;
        for ($i = 1; $i <= $pagaies; $i++) {
            $positions[] = $this->position(CrewRole::AIDE_PATRON, "aide_patron_{$i}", $pagaies === 1 ? 'Pagaie' : "Pagaie {$i}", BoatSide::Centre, null, 50, $y);
            $y += $step;
        }

        $positions[] = $this->position(CrewRole::PATRON, 'patron', 'Patron', BoatSide::Centre, null, 50, max(94.0, $y));

        return $positions;
    }

    /**
     * Brings the stored positions in line with the layout: existing codes are updated in place (their crew
     * assignments survive), new ones are created, positions no longer in the layout are removed with their
     * assignments.
     *
     * @return Collection<int, BoatPosition>
     */
    public function generate(BoatConfiguration $configuration): Collection
    {
        $roleIds = CrewRole::idsByCode();

        return DB::transaction(function () use ($configuration, $roleIds) {
            $layout = $this->layout($configuration);
            $codes = array_column($layout, 'code');

            $obsolete = $configuration->positions()->whereNotIn('code', $codes)->pluck('id');
            if ($obsolete->isNotEmpty()) {
                CrewAssignment::withTrashed()->whereIn('boat_position_id', $obsolete)->forceDelete();
                BoatPosition::query()->whereIn('id', $obsolete)->delete();
            }

            $positions = collect();

            foreach ($layout as $index => $spec) {
                $positions->push($configuration->positions()->updateOrCreate(
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

            return $positions;
        });
    }

    /** Index of a fond / écopeur seat ("fond_3" => 3), null for other seats. */
    public static function fondIndex(string $code): ?int
    {
        return preg_match('/^fond_(\d+)$/', $code, $matches) ? (int) $matches[1] : null;
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
