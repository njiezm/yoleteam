<?php

namespace App\Http\Controllers;

use App\Enums\RaceResultStatus;
use App\Enums\RaceType;
use App\Http\Requests\RaceRequest;
use App\Models\Boat;
use App\Models\Race;
use App\Models\RaceResult;
use App\Models\RaceStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RaceController extends Controller
{
    public function index(Request $request): View
    {
        $associationId = $request->user()->association_id;

        $races = Race::query()
            ->forAssociation($associationId)
            ->withCount('stages')
            ->with('results.boat')
            ->orderByDesc('start_date')
            ->get();

        $next = $races
            ->filter(fn (Race $race) => $race->isUpcoming())
            ->sortBy('start_date')
            ->first();

        return view('races.index', ['races' => $races, 'next' => $next]);
    }

    public function create(): View
    {
        return view('races.create', ['race' => new Race([
            'type' => RaceType::Regate,
            'season' => today()->year,
        ])]);
    }

    public function store(RaceRequest $request): RedirectResponse
    {
        $race = Race::create([...$request->validated(), 'association_id' => $request->user()->association_id]);

        return redirect()->route('races.show', $race)->with('status', 'Régate enregistrée');
    }

    public function show(Request $request, Race $race): View
    {
        $race->load('stages.results.boat');

        $results = $race->stages->flatMap->results;

        /** @var Collection<int, Boat> $boats Boats with at least one result in this race. */
        $boats = $results->pluck('boat')->unique('id')->sortBy('name')->values();
        $boat = $boats->firstWhere('id', $request->integer('boat')) ?? $boats->first();

        $rows = $race->stages->map(fn (RaceStage $stage) => [
            'stage' => $stage,
            'result' => $boat ? $stage->results->firstWhere('boat_id', $boat->id) : null,
        ]);

        $boatResults = $rows->pluck('result')->filter();
        $best = $boatResults
            ->filter(fn (RaceResult $result) => $result->isClassified() && $result->rank !== null)
            ->sortBy('rank')
            ->first();

        return view('races.show', [
            'race' => $race,
            'boats' => $boats,
            'boat' => $boat,
            'rows' => $rows,
            'stats' => [
                'points' => (float) $boatResults->sum('points'),
                'best' => $best,
                'bestStage' => $best ? $race->stages->firstWhere('id', $best->race_stage_id) : null,
                'sailed' => $boatResults->where('status', '!=', RaceResultStatus::NonPartant)->count(),
                'abandons' => $boatResults->where('status', RaceResultStatus::Abandon)->count(),
                'disqualified' => $boatResults->where('status', RaceResultStatus::Disqualifie)->count(),
            ],
            'chart' => $this->rankChart($rows, (int) ($results->max('rank') ?? 0)),
            'resultBoats' => $request->user()->can('manage')
                ? $this->resultBoats($request->user()->association_id, $results->pluck('boat_id')->unique())
                : collect(),
        ]);
    }

    public function edit(Race $race): View
    {
        return view('races.edit', ['race' => $race]);
    }

    public function update(RaceRequest $request, Race $race): RedirectResponse
    {
        $race->update($request->validated());

        return redirect()->route('races.show', $race)->with('status', 'Régate enregistrée');
    }

    public function destroy(Race $race): RedirectResponse
    {
        $race->delete();

        return redirect()->route('races.index')->with('status', 'Régate supprimée');
    }

    /**
     * Points of the "rank per stage" line chart (viewBox 300 × 150, rank 1 at the top).
     *
     * @param  Collection<int, array{stage: RaceStage, result: RaceResult|null}>  $rows
     * @return array{points: list<array{x: float, y: float, rank: int, number: int}>, ticks: list<array{y: float, rank: int}>, labels: list<array{x: float, number: int}>}|null
     */
    private function rankChart(Collection $rows, int $maxRank): ?array
    {
        $ranked = $rows->filter(fn (array $row) => $row['result']?->rank !== null);

        if ($ranked->isEmpty()) {
            return null;
        }

        $maxRank = max($maxRank, 5);
        $count = $rows->count();
        $x = fn (int $index) => round($count > 1 ? 30 + $index * 260 / ($count - 1) : 160, 1);
        $y = fn (int $rank) => round(10 + ($rank - 1) * 110 / ($maxRank - 1), 1);

        $ticks = collect([1, (int) round($maxRank / 3), (int) round($maxRank * 2 / 3), $maxRank])
            ->unique()
            ->map(fn (int $rank) => ['y' => $y($rank), 'rank' => $rank])
            ->values()
            ->all();

        return [
            'points' => $ranked->map(fn (array $row, int $index) => [
                'x' => $x($index),
                'y' => $y($row['result']->rank),
                'rank' => $row['result']->rank,
                'number' => $row['stage']->number,
            ])->values()->all(),
            'ticks' => $ticks,
            'labels' => $rows->map(fn (array $row, int $index) => ['x' => $x($index), 'number' => $row['stage']->number])->values()->all(),
        ];
    }

    /**
     * Active boats of the association, plus inactive ones that already have results in this race.
     *
     * @param  Collection<int, int>  $boatIdsWithResults
     * @return Collection<int, Boat>
     */
    private function resultBoats(int $associationId, Collection $boatIdsWithResults): Collection
    {
        return Boat::query()
            ->forAssociation($associationId)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $boatIdsWithResults))
            ->orderBy('name')
            ->get();
    }
}
