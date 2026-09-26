<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Outing;
use App\Services\AttendanceAlerts;
use App\Services\AttendanceStats;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoryController extends Controller
{
    /** Number of outings displayed in the attendance grid. */
    private const GRID_OUTINGS = 10;

    public function index(Request $request, AttendanceStats $stats, AttendanceAlerts $alerts): View
    {
        $associationId = $request->user()->association_id;
        [$period, $since] = $this->period($request);
        $type = OutingType::tryFrom((string) $request->query('type'));

        $outings = $this->recordedOutings($associationId, $since, $type);
        $gridOutings = $outings->take(self::GRID_OUTINGS)->reverse()->values();
        $members = $this->activeMembers($associationId);
        $perMember = $stats->perMember($associationId, $since, $type?->value);
        $overall = $stats->overall($associationId, $since, $type?->value);

        $ranked = $members
            ->filter(fn (Member $member) => ($perMember[$member->id]['rate'] ?? null) !== null)
            ->map(fn (Member $member) => ['member' => $member, ...$perMember[$member->id]]);

        return view('history.index', [
            'alerts' => $alerts->for($associationId),
            'filters' => ['period' => $period, 'type' => $type?->value],
            'outings' => $outings,
            'gridOutings' => $gridOutings,
            'grid' => $this->statuses($gridOutings),
            'members' => $members,
            'perMember' => $perMember,
            'overall' => $overall,
            'onSitePerOuting' => $outings->isNotEmpty() ? ($overall['present'] + $overall['retard']) / $outings->count() : null,
            'unexcusedMembers' => $perMember->filter(fn (array $row) => $row['absent'] > 0)->count(),
            'mostAssiduous' => $ranked->sortByDesc('rate')->take(5)->values(),
            'toFollowUp' => $ranked->sortBy('rate')->take(5)->values(),
        ]);
    }

    public function export(Request $request, AttendanceStats $stats): StreamedResponse
    {
        $associationId = $request->user()->association_id;
        [, $since] = $this->period($request);
        $type = OutingType::tryFrom((string) $request->query('type'));

        $outings = $this->recordedOutings($associationId, $since, $type)->reverse()->values();
        $statuses = $this->statuses($outings);
        $members = $this->activeMembers($associationId);
        $perMember = $stats->perMember($associationId, $since, $type?->value);

        return response()->streamDownload(function () use ($outings, $statuses, $members, $perMember): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Membre',
                ...$outings->map(fn (Outing $outing) => $outing->date->format('d/m/Y').' '.$outing->title)->all(),
                'Taux de présence',
            ], ';');

            foreach ($members as $member) {
                $rate = $perMember[$member->id]['rate'] ?? null;
                fputcsv($handle, [
                    $member->full_name,
                    ...$outings->map(fn (Outing $outing) => $statuses->get($member->id)?->get($outing->id)?->label() ?? '')->all(),
                    $rate !== null ? $rate.' %' : '',
                ], ';');
            }

            fclose($handle);
        }, 'presences-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: string, 1: CarbonInterface}
     */
    private function period(Request $request): array
    {
        return $request->query('period') === 'season'
            ? ['season', today()->startOfYear()]
            : ['30', today()->subDays(30)];
    }

    /**
     * Non-cancelled outings of the period with at least one attendance, most recent first.
     *
     * @return Collection<int, Outing>
     */
    private function recordedOutings(int $associationId, CarbonInterface $since, ?OutingType $type): Collection
    {
        return Outing::query()
            ->forAssociation($associationId)
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereDate('date', '>=', $since)
            ->whereDate('date', '<=', today())
            ->when($type, fn ($query) => $query->where('type', $type))
            ->has('attendances')
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();
    }

    /**
     * @return Collection<int, Member>
     */
    private function activeMembers(int $associationId): Collection
    {
        return Member::query()
            ->forAssociation($associationId)
            ->active()
            ->with('crewRoles')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Attendance statuses indexed by member then outing.
     *
     * @param  Collection<int, Outing>  $outings
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, AttendanceStatus>>
     */
    private function statuses(Collection $outings): \Illuminate\Support\Collection
    {
        return Attendance::query()
            ->whereIn('outing_id', $outings->modelKeys())
            ->get(['member_id', 'outing_id', 'status'])
            ->groupBy('member_id')
            ->map(fn ($rows) => $rows->pluck('status', 'outing_id'));
    }
}
