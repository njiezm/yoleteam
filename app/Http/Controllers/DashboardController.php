<?php

namespace App\Http\Controllers;

use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Boat;
use App\Models\Member;
use App\Models\Outing;
use App\Services\AttendanceAlerts;
use App\Services\AttendanceStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AttendanceStats $stats, AttendanceAlerts $alerts): View
    {
        $associationId = $request->user()->association_id;
        $activeMembers = Member::query()->forAssociation($associationId)->active()->count();

        $training = $this->currentTraining($associationId);
        $training?->load(['crewPlans' => fn ($query) => $query->withCount('assignments'), 'crewPlans.boat', 'crewPlans.configuration.positions']);

        return view('dashboard', [
            'offlineUrls' => $this->offlineUrls($associationId),
            'training' => $training,
            'trainingCounts' => $training ? $stats->forOuting($training) : null,
            'activeMembers' => $activeMembers,
            'rate30' => $stats->overall($associationId, today()->subDays(30))['rate'],
            'outingsThisMonth' => Outing::query()->forAssociation($associationId)
                ->where('status', '!=', OutingStatus::Annulee)
                ->whereBetween('date', [today()->startOfMonth(), today()->endOfMonth()])
                ->count(),
            'alerts' => $alerts->for($associationId),
            'upcoming' => Outing::query()
                ->forAssociation($associationId)
                ->with('crewPlans.boat')
                ->where('status', '!=', OutingStatus::Annulee)
                ->whereDate('date', '>=', today())
                ->when($training, fn ($query) => $query->whereKeyNot($training->id))
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(4)
                ->get(),
            'unavailableBoats' => Boat::query()->forAssociation($associationId)->where('is_active', false)->get(),
        ]);
    }

    /** Today's training, otherwise the next one, otherwise the latest one. */
    private function currentTraining(int $associationId): ?Outing
    {
        $query = fn () => Outing::query()
            ->forAssociation($associationId)
            ->where('type', OutingType::Entrainement)
            ->where('status', '!=', OutingStatus::Annulee);

        return $query()->whereDate('date', today())->orderBy('start_time')->first()
            ?? $query()->whereDate('date', '>', today())->orderBy('date')->orderBy('start_time')->first()
            ?? $query()->whereDate('date', '<', today())->orderByDesc('date')->first();
    }

    /**
     * Pages the service worker keeps for offline use: the coming week's outings (appel, crew plans, edit form),
     * members, regattas, boats and the pages used to fill forms offline.
     *
     * @return list<string>
     */
    private function offlineUrls(int $associationId): array
    {
        $outings = Outing::query()
            ->forAssociation($associationId)
            ->with('crewPlans')
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereBetween('date', [today(), today()->addDays(7)])
            ->orderBy('date')
            ->limit(6)
            ->get();

        $path = fn (string $name, mixed $parameters = []) => route($name, $parameters, false);

        return collect([
            $path('attendance.today'), $path('crew-plans.today'), $path('sync.index'),
            $path('outings.index'), $path('outings.create'), $path('outings.offline'), $path('members.index'),
            $path('boats.index'), $path('attendance.stats'), $path('statistics.index'), $path('more'), $path('profile.edit'),
        ])
            ->when(Gate::allows('manage'), fn ($urls) => $urls->push($path('members.create')))
            ->concat($outings->flatMap(fn (Outing $outing) => [
                $path('outings.show', $outing),
                $path('outings.edit', $outing),
                $path('attendance.edit', $outing),
                ...$outing->crewPlans->map(fn ($plan) => $path('crew-plans.edit', [$outing, $plan])),
            ]))
            ->concat(Member::query()->forAssociation($associationId)->active()->pluck('id')->map(fn (int $id) => $path('members.show', $id)))
            ->concat(Boat::query()->forAssociation($associationId)->where('is_active', true)->pluck('id')->map(fn (int $id) => $path('boats.show', $id)))
            ->values()
            ->all();
    }
}
