<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\OutingStatus;
use App\Models\Attendance;
use App\Models\Outing;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Attendance aggregates. "On site" = présent or retard; the rate is on-site records / recorded outings.
 */
class AttendanceStats
{
    /**
     * Per-member status counts and rate.
     *
     * @return Collection<int, array{present: int, retard: int, excuse: int, absent: int, total: int, rate: int|null}>
     */
    public function perMember(int $associationId, ?CarbonInterface $since = null, ?string $outingType = null): Collection
    {
        return $this->recorded($associationId, $since, $outingType)
            ->selectRaw('attendances.member_id, attendances.status, count(*) as aggregate')
            ->groupBy('attendances.member_id', 'attendances.status')
            ->toBase()
            ->get()
            ->groupBy('member_id')
            ->map(fn (Collection $rows) => $this->summarise($rows->pluck('aggregate', 'status')->all()));
    }

    /**
     * Totals for all members.
     *
     * @return array{present: int, retard: int, excuse: int, absent: int, total: int, rate: int|null}
     */
    public function overall(int $associationId, ?CarbonInterface $since = null, ?string $outingType = null): array
    {
        return $this->summarise(
            $this->recorded($associationId, $since, $outingType)
                ->selectRaw('attendances.status, count(*) as aggregate')
                ->groupBy('attendances.status')
                ->toBase()
                ->pluck('aggregate', 'status')
                ->all()
        );
    }

    /**
     * Status counts for one outing, plus how many active members are still to be recorded.
     *
     * @return array{present: int, retard: int, excuse: int, absent: int, total: int, rate: int|null}
     */
    public function forOuting(Outing $outing): array
    {
        return $this->summarise(
            $outing->attendances()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->toBase()
                ->pluck('aggregate', 'status')
                ->all()
        );
    }

    /**
     * @param  array<string, int|string>  $counts  status => count
     * @return array{present: int, retard: int, excuse: int, absent: int, total: int, rate: int|null}
     */
    public function summarise(array $counts): array
    {
        $summary = [];
        foreach (AttendanceStatus::cases() as $status) {
            $summary[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $total = array_sum($summary);
        $onSite = $summary[AttendanceStatus::Present->value] + $summary[AttendanceStatus::Retard->value];

        return [...$summary, 'total' => $total, 'rate' => $total > 0 ? (int) round($onSite / $total * 100) : null];
    }

    /** Colour used for attendance rates (green / yellow / red). */
    public static function rateColor(?int $rate): string
    {
        return match (true) {
            $rate === null => '#CBD5E1',
            $rate >= 85 => '#10B981',
            $rate >= 70 => '#F5B700',
            default => '#EF4444',
        };
    }

    /** @return Builder<Attendance> */
    private function recorded(int $associationId, ?CarbonInterface $since, ?string $outingType): Builder
    {
        return Attendance::query()
            ->join('outings', 'outings.id', '=', 'attendances.outing_id')
            ->where('outings.association_id', $associationId)
            ->whereNull('outings.deleted_at')
            ->where('outings.status', '!=', OutingStatus::Annulee->value)
            ->whereDate('outings.date', '<=', today())
            ->when($since, fn (Builder $query) => $query->whereDate('outings.date', '>=', $since))
            ->when($outingType, fn (Builder $query) => $query->where('outings.type', $outingType));
    }
}
