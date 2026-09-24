<?php

namespace App\Http\Controllers;

use App\Enums\OutingStatus;
use App\Enums\OutingType;
use App\Models\Boat;
use App\Models\Member;
use App\Models\Outing;
use App\Models\Race;
use App\Services\AttendanceStats;
use App\Services\CrewPlanPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AttendanceStats $stats, CrewPlanPresenter $presenter): View
    {
        $associationId = $request->user()->association_id;
        $activeMembers = Member::query()->forAssociation($associationId)->active()->count();

        $current = Outing::current($associationId);
        $current?->load(['crewPlans' => fn ($query) => $query->withCount('assignments'), 'crewPlans.boat', 'crewPlans.configuration.positions']);

        $upcoming = Outing::query()
            ->forAssociation($associationId)
            ->with('crewPlans.boat')
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereDate('date', '>=', today())
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->orderBy('date')
            ->limit(5)
            ->get();

        $recentOutings = Outing::query()
            ->forAssociation($associationId)
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereDate('date', '<=', today())
            ->has('attendances')
            ->orderByDesc('date')
            ->limit(8)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Outing $outing) => ['outing' => $outing, 'rate' => $stats->forOuting($outing)['rate'] ?? 0]);

        $nextRegatta = Outing::query()
            ->forAssociation($associationId)
            ->where('type', OutingType::Regate)
            ->where('status', '!=', OutingStatus::Annulee)
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->first();

        return view('dashboard', [
            'offlineUrls' => $this->offlineUrls($associationId),
            'current' => $current,
            'currentCounts' => $current ? $stats->forOuting($current) : null,
            'activeMembers' => $activeMembers,
            'upcoming' => $upcoming,
            'recentOutings' => $recentOutings,
            'rate30' => $stats->overall($associationId, today()->subDays(30))['rate'],
            'outingsThisMonth' => Outing::query()->forAssociation($associationId)
                ->where('status', '!=', OutingStatus::Annulee)
                ->whereBetween('date', [today()->startOfMonth(), today()->endOfMonth()])
                ->count(),
            'nextRegatta' => $nextRegatta,
            'racesThisSeason' => Race::query()->forAssociation($associationId)->where('season', today()->year)->count(),
            'unavailableBoats' => Boat::query()->forAssociation($associationId)->where('is_active', false)->get(),
            'presenter' => $presenter,
        ]);
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
            $path('outings.index'), $path('outings.create'), $path('members.index'), $path('races.index'),
            $path('boats.index'), $path('history.index'), $path('more'), $path('profile.edit'),
        ])
            ->when(Gate::allows('manage'), fn ($urls) => $urls->push($path('members.create')))
            ->concat($outings->flatMap(fn (Outing $outing) => [
                $path('outings.show', $outing),
                $path('outings.edit', $outing),
                $path('attendance.edit', $outing),
                ...$outing->crewPlans->map(fn ($plan) => $path('crew-plans.edit', [$outing, $plan])),
            ]))
            ->concat(Member::query()->forAssociation($associationId)->active()->pluck('id')->map(fn (int $id) => $path('members.show', $id)))
            ->concat(Race::query()->forAssociation($associationId)->where('season', today()->year)->pluck('id')->map(fn (int $id) => $path('races.show', $id)))
            ->concat(Boat::query()->forAssociation($associationId)->where('is_active', true)->pluck('id')->map(fn (int $id) => $path('boats.show', $id)))
            ->values()
            ->all();
    }
}
