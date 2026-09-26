<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Member;
use App\Models\Outing;
use Illuminate\Support\Collection;

/**
 * Attendance alerts on trainings (entraînements), explained to users on the attendance statistics page:
 * - "3 manqués d'affilée": the member missed the last 3 trainings in a row (absent, or not recorded while the
 *   appel was done). An excused training is neutral: it neither counts as missed nor breaks the series.
 * - "Irrégulier": on the last 10 trainings (excused ones set aside), the member was on site (présent or en
 *   retard) less than 60 % of the time — only once at least 5 trainings count.
 */
class AttendanceAlerts
{
    public const CONSECUTIVE_MISSES = 3;

    public const WINDOW = 10;

    public const MIN_RATE = 60;

    public const MIN_COUNTED = 5;

    /**
     * @return Collection<int, array{member: Member, type: string, title: string, detail: string}>
     */
    public function for(int $associationId): Collection
    {
        $trainings = Outing::query()
            ->forAssociation($associationId)
            ->where('type', OutingType::Entrainement)
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereDate('date', '<=', today())
            ->has('attendances') // the appel was done
            ->with('attendances:id,outing_id,member_id,status')
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->limit(30)
            ->get();

        if ($trainings->isEmpty()) {
            return collect();
        }

        $members = Member::query()->forAssociation($associationId)->active()->with('crewRoles')->orderBy('first_name')->get();

        return $members->flatMap(function (Member $member) use ($trainings) {
            // Most recent first; null = not recorded although the appel was done.
            $history = $trainings
                ->filter(fn (Outing $outing) => $member->created_at === null || $outing->date->endOfDay()->gte($member->created_at))
                ->map(fn (Outing $outing) => [
                    'outing' => $outing,
                    'status' => $outing->attendances->firstWhere('member_id', $member->id)?->status,
                ])
                ->reject(fn (array $row) => $row['status'] === AttendanceStatus::Excuse)
                ->values();

            $alerts = [];

            $streak = $history->takeWhile(fn (array $row) => $row['status'] === null || $row['status'] === AttendanceStatus::Absent);
            if ($streak->count() >= self::CONSECUTIVE_MISSES) {
                $alerts[] = [
                    'member' => $member,
                    'type' => 'consecutive',
                    'title' => $streak->count().' entraînements manqués d’affilée',
                    'detail' => 'Dernière présence : '.($history->first(fn (array $row) => $row['status']?->isOnSite())
                        ? $history->first(fn (array $row) => $row['status']?->isOnSite())['outing']->date->translatedFormat('j M')
                        : 'aucune récente'),
                ];
            }

            $window = $history->take(self::WINDOW);
            if ($window->count() >= self::MIN_COUNTED) {
                $onSite = $window->filter(fn (array $row) => $row['status']?->isOnSite())->count();
                $rate = (int) round($onSite / $window->count() * 100);
                if ($rate < self::MIN_RATE) {
                    $alerts[] = [
                        'member' => $member,
                        'type' => 'irregular',
                        'title' => 'Présence irrégulière',
                        'detail' => "{$onSite} présence(s) sur les {$window->count()} derniers entraînements ({$rate} %)",
                    ];
                }
            }

            return $alerts;
        })->values();
    }
}
