<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Member;
use App\Models\Outing;
use App\Services\AttendanceRecorder;
use App\Services\AttendanceStats;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /** Shortcut from the navigation: the appel of the current outing. */
    public function today(Request $request): RedirectResponse
    {
        $outing = Outing::current($request->user()->association_id);

        return $outing
            ? redirect()->route('attendance.edit', $outing)
            : redirect()->route('outings.create')->with('status', 'Créez d’abord une sortie pour faire l’appel');
    }

    public function edit(Outing $outing, AttendanceStats $stats): View
    {
        $outing->load('crewPlans.boat');
        $attendances = $outing->attendances()->get()->keyBy('member_id');

        return view('attendance.edit', [
            'outing' => $outing,
            'members' => $this->members($outing, $attendances->keys()->all()),
            'attendances' => $attendances,
            'counts' => $stats->forOuting($outing),
        ]);
    }

    public function update(UpdateAttendanceRequest $request, Outing $outing, AttendanceStats $stats, AttendanceRecorder $recorder): JsonResponse|RedirectResponse
    {
        $statuses = $request->validated('statuses', []);

        if ($request->boolean('all_present')) {
            $recorded = $outing->attendances()->pluck('member_id');
            Member::query()->forAssociation($outing->association_id)->active()
                ->whereNotIn('id', $recorded)
                ->pluck('id')
                ->each(function (int $memberId) use (&$statuses) {
                    $statuses[$memberId] ??= AttendanceStatus::Present->value;
                });
        }

        DB::transaction(function () use ($statuses, $outing, $request, $recorder) {
            foreach ($statuses as $memberId => $value) {
                $recorder->record($outing, (int) $memberId, $value ? AttendanceStatus::from($value) : null, $request->user()->id);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'counts' => $stats->forOuting($outing),
                'statuses' => $outing->attendances()->pluck('status', 'member_id'),
            ]);
        }

        return redirect()->route('attendance.edit', $outing)->with('status', 'Présences enregistrées');
    }

    /**
     * Active members, plus inactive ones already recorded for this outing.
     *
     * @param  list<int>  $recordedIds
     * @return Collection<int, Member>
     */
    private function members(Outing $outing, array $recordedIds): Collection
    {
        return Member::query()
            ->forAssociation($outing->association_id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $recordedIds))
            ->with('crewRoles')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
