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

        // Avant : cordes (2 voiles).
        foreach (array_slice([['premiere_corde', '1ère corde', 5], ['deuxieme_corde', '2ème corde', 20]], 0, min(2, $counts['cordes_count'])) as [$code, $label, $y]) {
            $positions[] = $this->position($code === 'premiere_corde' ? CrewRole::PREMIERE_CORDE : CrewRole::DEUXIEME_CORDE, $code, $label, BoatSide::Centre, null, 50, $y);
        }

        // Écoutes : une paire par voile (petite voile à l'avant, grande voile au milieu).
        $ecoutes = max(1, $counts['ecoute_count']);
        $sheets = $sails >= 2
            ? [['ecoute_pv', 'Écoute petite voile', 33, intdiv($ecoutes, 2)], ['ecoute_gv', 'Écoute grande voile', 48, $ecoutes - intdiv($ecoutes, 2)]]
            : [['ecoute', 'Écoute', 36, $ecoutes]];

        foreach ($sheets as [$prefix, $label, $y, $count]) {
            for ($i = 1; $i <= $count; $i++) {
                $x = $count === 1 ? 50 : 50 + (($i - 1) % 2 === 0 ? -8 : 8);
                $positions[] = $this->position(CrewRole::ECOUTE, "{$prefix}_{$i}", $count === 1 ? $label : "{$label} {$i}", BoatSide::Centre, null, $x, $y + intdiv($i - 1, 2) * 7);
            }
        }

        // Bwa dressés : tous au vent, du plus avant (1) au plus arrière.
        $bwa = max(1, $counts['bwa_count']);
        [$first, $last] = $sails >= 2 ? [24.0, 80.0] : [20.0, 80.0];
        for ($i = 1; $i <= $bwa; $i++) {
            $y = $bwa > 1 ? $first + ($i - 1) * ($last - $first) / ($bwa - 1) : ($first + $last) / 2;
            // The first dresseur is "les yeux du patron"; the last one stays in rappel longest when tacking.
            $label = "Bwa dressé {$i}".match (true) {
                $bwa > 1 && $i === 1 => ' (premier)',
                $bwa > 1 && $i === $bwa => ' (dernier)',
                default => '',
            };
            $positions[] = $this->position(CrewRole::DRESSEUR, "bwa_{$i}", $label, BoatSide::Babord, $i, self::BABORD_X, round($y, 2));
        }

        // Fonds / écopeurs : le plan d'équipage choisit combien de places sont utilisées.
        $fondStart = $sails >= 2 ? 57 : 50;
        for ($i = 1; $i <= self::MAX_FONDS; $i++) {
            $positions[] = $this->position(CrewRole::ECOPEUR, "fond_{$i}", "Fond / écopeur {$i}", BoatSide::Centre, null, 50, $fondStart + ($i - 1) * 7, true);
        }

        // Arrière : pagaies puis patron.
        $pagaies = min(3, max(0, $counts['pagaie_count']));
        $pagaieX = match ($pagaies) {
            1 => [50],
            2 => [40, 60],
            3 => [36, 50, 64],
            default => [],
        };
        foreach ($pagaieX as $index => $x) {
            $side = $x < 50 ? BoatSide::Babord : ($x > 50 ? BoatSide::Tribord : BoatSide::Centre);
            $positions[] = $this->position(CrewRole::AIDE_PATRON, 'aide_patron_'.($index + 1), $pagaies === 1 ? 'Pagaie' : 'Pagaie '.($index + 1), $side, null, $x, 83);
        }

        $positions[] = $this->position(CrewRole::PATRON, 'patron', 'Patron', BoatSide::Centre, null, 50, 94);

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
