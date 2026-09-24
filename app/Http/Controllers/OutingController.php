<?php

namespace App\Http\Controllers;

use App\Enums\CrewPlanStatus;
use App\Enums\OutingStatus;
use App\Http\Requests\OutingRequest;
use App\Models\Boat;
use App\Models\Member;
use App\Models\Outing;
use App\Models\RaceStage;
use App\Services\AttendanceStats;
use App\Services\CrewPlanEditorData;
use App\Services\CrewPlanPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OutingController extends Controller
{
    public function index(Request $request): View
    {
        $associationId = $request->user()->association_id;
        $filter = in_array($request->query('filtre'), ['a-venir', 'passees', 'toutes'], true) ? $request->query('filtre') : 'a-venir';

        $month = rescue(fn () => Carbon::createFromFormat('!Y-m', (string) $request->query('mois')), today()->startOfMonth(), false);

        $outings = Outing::query()
            ->forAssociation($associationId)
            ->with('crewPlans.boat')
            ->when($filter === 'a-venir', fn ($query) => $query->whereDate('date', '>=', today())->orderBy('date')->orderBy('start_time'))
            ->when($filter === 'passees', fn ($query) => $query->whereDate('date', '<', today())->orderByDesc('date'))
            ->when($filter === 'toutes', fn ($query) => $query->orderByDesc('date'))
            ->paginate(20)
            ->withQueryString();

        $calendar = Outing::query()
            ->forAssociation($associationId)
            ->whereBetween('date', [$month->copy()->startOfWeek(), $month->copy()->endOfMonth()->endOfWeek()])
            ->get(['id', 'date', 'type', 'status', 'title'])
            ->groupBy(fn (Outing $outing) => $outing->date->toDateString());

        return view('outings.index', compact('outings', 'filter', 'month', 'calendar'));
    }

    public function create(Request $request): View
    {
        return view('outings.create', [
            'outing' => new Outing(['date' => $request->date('date') ?? today(), 'start_time' => '06:00', 'end_time' => '09:00']),
            'boats' => Boat::query()->forAssociation($request->user()->association_id)->with('configurations')->orderBy('name')->get(),
            'stages' => $this->stageOptions($request->user()->association_id),
        ]);
    }

    public function store(OutingRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $outing = DB::transaction(function () use ($request, $data) {
            $outing = Outing::create([
                ...collect($data)->except(['boats', 'configurations'])->all(),
                'association_id' => $request->user()->association_id,
                'status' => OutingStatus::Planifiee,
                'created_by' => $request->user()->id,
            ]);

            $boats = Boat::query()->whereIn('id', $data['boats'] ?? [])->with('configurations')->get();

            foreach ($boats as $boat) {
                $requested = (int) ($data['configurations'][$boat->id] ?? 0);
                $configuration = $boat->configurations->firstWhere('id', $requested)
                    ?? $boat->configurations->firstWhere('is_default', true)
                    ?? $boat->configurations->first();

                if ($configuration) {
                    $outing->crewPlans()->create([
                        'boat_id' => $boat->id,
                        'boat_configuration_id' => $configuration->id,
                        'status' => CrewPlanStatus::Brouillon,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }

            return $outing;
        });

        return redirect()->route('outings.show', $outing)->with('status', 'Sortie créée');
    }

    public function show(Request $request, Outing $outing, AttendanceStats $stats, CrewPlanPresenter $presenter, CrewPlanEditorData $editorData): View
    {
        $outing->load([
            'creator',
            'raceStage.race',
            'crewPlans' => fn ($query) => $query->with(['boat', 'configuration.positions.crewRole', 'assignments.position', 'assignments.member.crewRoles'])->orderBy('id'),
        ]);

        $associationId = $request->user()->association_id;
        $engagedBoatIds = $outing->crewPlans->pluck('boat_id');
        $availableBoats = Boat::query()->forAssociation($associationId)
            ->where('is_active', true)
            ->whereNotIn('id', $engagedBoatIds)
            ->with('configurations.positions.crewRole')
            ->orderBy('name')
            ->get();

        return view('outings.show', [
            'outing' => $outing,
            'counts' => $stats->forOuting($outing),
            'activeMembers' => Member::query()->forAssociation($associationId)->active()->count(),
            'availableBoats' => $availableBoats,
            // Editor data for each boat that can still be engaged: lets the patron create a plan offline.
            'planTemplates' => $availableBoats->mapWithKeys(fn (Boat $boat) => [$boat->id => $editorData->build($outing, $boat, null, $associationId)]),
            'windOptions' => CrewPlanPresenter::windOptions(),
            'presenter' => $presenter,
        ]);
    }

    public function edit(Request $request, Outing $outing): View
    {
        return view('outings.edit', [
            'outing' => $outing,
            'stages' => $this->stageOptions($request->user()->association_id),
        ]);
    }

    public function update(OutingRequest $request, Outing $outing): RedirectResponse
    {
        $outing->update($request->validated());

        return redirect()->route('outings.show', $outing)->with('status', 'Sortie mise à jour');
    }

    public function destroy(Outing $outing): RedirectResponse
    {
        $outing->delete();

        return redirect()->route('outings.index')->with('status', 'Sortie supprimée');
    }

    /** @return array<int, string> stage id => "Race · Étape n" */
    private function stageOptions(int $associationId): array
    {
        return RaceStage::query()
            ->whereHas('race', fn ($query) => $query->forAssociation($associationId))
            ->with('race')
            ->orderByDesc('date')
            ->get()
            ->mapWithKeys(fn (RaceStage $stage) => [$stage->id => "{$stage->race->name} · {$stage->name}"])
            ->all();
    }
}
